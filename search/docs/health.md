# Health

Checking index health is an important part of managing an index for a site. VIP Search runs these on a regular schedule and post irregularities into monitored channels. These commands can also be run via WP CLI at any time and will output the results into your local terminal.

## Validate Counts <a name='validate-counts'></a>

There are three commands for checking if the counts from VIP Search match the database:

- `wp vip-search health validate-counts`
    - Compares the user and post counts from the database against VIP Search and displays the results.
- `wp vip-search health validate-posts-count [--version=<int>] [--network-wide]`
    - Compares the post counts from the database against VIP Search and displays the results.
- `wp vip-search health validate-users-count [--version=<int>] [--network-wide]`
    - Compares the user count from the database against VIP Search and displays the results.

Post count checks checks against all indexable post types.

## Validate Contents <a name='validate-contents'></a>

There is one command for seeing what the inconsistencies actually are:

- `wp vip-search health validate-contents [--inspect] [--start_post_id=<int>] [--last_post_id=<int>] [--batch_size=<int>] [--max_diff_size=<int>] [--format=<string>] [--do-not-heal] [--silent]`

This command will go through all posts by from 1 to the current max post ID(whether a post exists at a specific ID or not) and check if the post data from the database matches the VIP Search index. If it doesn't, the command will adjust the index by re-indexing or deleting depending on if it's missing, the data isn't consistent with the database, or it shouldn't exist.

The various flags can modify the default behaviour.

- `[--inspect]` 
    - Gives more verbose output for index inconsistencies
- `[--start_post_id=<int>]`
    - The starting post id (defaults to 1). The lower bound of the range of post IDs that will be checked.
- `[--last_post_id=<int>]`
    - The last post id to check. The upper bound of the range of post IDs that will be checked.
- `[--batch_size=<int>]`
    - The number of posts that will be checked in one iteration. Default 500.
- `[--max_diff_size=<int>]`
    - The maximum number of inconsistencies to find before exiting. Default 1000.
- `[--format=<string>]` 
    - The format to output the inconsistencies with. Possible values: table json csv yaml ids count
- `[--do-not-heal]` 
    - Do not try to automatically fix any inconsistencies found.
- `[--silent]`
    - Silences all non-error output except for the final results
