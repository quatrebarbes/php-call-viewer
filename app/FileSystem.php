<?php

namespace App;

class FileSystem
{

    protected const BLACKLISTED_FOLDER = '/\/vendor$/';

    public static function createFolder(string $dirPath)
    {
        if (!is_dir($dirPath)) {
            exec("mkdir -p \"$dirPath\"");
        }
    }

    public static function emptyFolder(string $dirPath)
    {
        self::delete($dirPath, false);
    }

    public static function deleteFolder(string $dirPath)
    {
        self::delete($dirPath, true);
    }

    // see: https://www.tutorialspoint.com/how-to-recursively-delete-a-directory-and-its-entire-contents-files-plus-sub-dirs-in-php
    private static function delete(string $dirPath, bool $selfDelete)
    {
        if (is_dir($dirPath)) {
            $files = scandir($dirPath);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    $filePath = $dirPath . '/' . $file;
                    if (is_dir($filePath)) {
                        self::delete($filePath, true);
                    } else {
                        unlink($filePath);
                    }
                }
            }
            if ($selfDelete) {
                rmdir($dirPath);
            }
        }
    }

    public static function listFiles($folders)
    {
        $res = [];

        foreach ($folders as $folder) {
            $res = array_merge($res, self::recursivelyListFiles($folder));
        }

        return $res;
    }

    private static function recursivelyListFiles(string $folder)
    {
        $res = [];

        $files = glob($folder . '/*');
        foreach ($files as $file) {
            if (is_file($file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $res[$file] = file_get_contents($file);
            } elseif (
                is_dir($file) &&
                !preg_match(self::BLACKLISTED_FOLDER, $file)
            ) {
                $res = array_merge($res, self::recursivelyListFiles($file));
            }
        }

        return $res;
    }

    public static function readFile($filePath)
    {
        return file_get_contents($filePath);
    }

    public static function writeFile($filePath, $fileContent)
    {
        file_put_contents($filePath, $fileContent);
    }
}
