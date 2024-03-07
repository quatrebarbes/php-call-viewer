#!/bin/sh

echo ""
echo "# $CI_MERGE_REQUEST_TITLE"
echo ""
echo "+--------------------------------------------------------+"
echo "| Base commit '$CI_MERGE_REQUEST_DIFF_BASE_SHA' |"
echo "| Head commit '$CI_COMMIT_SHA' |"
echo "+--------------------------------------------------------+"
echo ""

bin/php-call-viewer --path="$CI_PROJECT_DIR" --tmpPath="/opt/.tmp" --outPath="/opt/.uml" --title="$CI_MERGE_REQUEST_TITLE" --filename="$CI_JOB_ID" --repo="$CI_REPOSITORY_URL" --base="$CI_MERGE_REQUEST_DIFF_BASE_SHA" --head="$CI_MERGE_REQUEST_SOURCE_BRANCH_NAME"

upload=$(curl -X POST "$CI_API_V4_URL/projects/$CI_MERGE_REQUEST_PROJECT_ID/uploads" \
     -H "PRIVATE-TOKEN: $GITLAB_TOKEN" \
     -F "file=@/opt/.uml/$CI_JOB_ID.svg" | jq -r '.markdown')

curl -L -X POST "$CI_API_V4_URL/projects/$CI_MERGE_REQUEST_PROJECT_ID/merge_requests/$CI_MERGE_REQUEST_IID/notes" \
     -H "PRIVATE-TOKEN: $GITLAB_TOKEN" \
     -H "Content-Type: application/json" \
     --data-raw "{ \"body\": \"$upload\" }"
