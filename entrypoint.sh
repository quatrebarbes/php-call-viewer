#!/bin/sh

echo "Merge Request '$CI_MERGE_REQUEST_TITLE'"
echo "From branch '$CI_MERGE_REQUEST_SOURCE_BRANCH_NAME'"
echo "To branch '$CI_MERGE_REQUEST_TARGET_BRANCH_NAME'"

bin/php-call-viewer --path="$CI_PROJECT_DIR" --title="$CI_MERGE_REQUEST_TITLE" --filename="$CI_MERGE_REQUEST_TITLE.svg" --target="$CI_MERGE_REQUEST_SOURCE_BRANCH_NAME" --source="$CI_MERGE_REQUEST_TARGET_BRANCH_NAME"

$upload=$(curl -X POST "$CI_PROJECT_URL/upload" \
     -H "Content-Type: multipart/form-data; boundary=----" \
     -H "PRIVATE-TOKEN: $GITLAB_TOKEN" \
     --data-binary @$CI_MERGE_REQUEST_TITLE.svg \
     | jq '.markdown')

curl -L -X POST "$CI_API_V4_URL/projects/$CI_MERGE_REQUEST_PROJECT_ID/merge_requests/$CI_MERGE_REQUEST_IID/notes" \
     -H "PRIVATE-TOKEN: $GITLAB_TOKEN" \
     -H "Content-Type: application/json" \
     --data-raw "{ \"body\": $upload }"
