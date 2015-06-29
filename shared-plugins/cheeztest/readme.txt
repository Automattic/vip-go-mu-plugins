  ______   __                 ________           __     
 /      \ /  |               /        |         /  |    
/$$$$$$  |$$ |____   ________$$$$$$$$/_______  _$$ |_   
$$ |  $$/ $$      \ /        |  $$ | /       |/ $$   |  
$$ |      $$$$$$$  |$$$$$$$$/   $$ |/$$$$$$$/ $$$$$$/   
$$ |   __ $$ |  $$ |  /  $$/    $$ |$$      \   $$ | __ 
$$ \__/  |$$ |  $$ | /$$$$/__   $$ | $$$$$$  |  $$ |/  |
$$    $$/ $$ |  $$ |/$$      |  $$ |/     $$/   $$  $$/ 
 $$$$$$/  $$/   $$/ $$$$$$$$/   $$/ $$$$$$$/     $$$$/  

Create new Batcache-compatible server-side A/B tests

A joint LOL by the fine folks I Can Has Cheezburger (http://www.cheezburger.com) and Automattic (http://automattic.com)

Main class from which all A/B tests are inherited. Enables fast setup
of A/B tests - upon initialization, the basic order of execution is:
set test name > check if user is qualified to participate > check
if user has been assigned a segment, and assign if not > assign user
to group > execute 'action' callback if present.

User's qualification, segment, and group tests are done in batcache
so as to ensure correct cache variants are served.

User's segment is assigned via client-side javascript. Mutliple test
segments are assigned at once - so if a user is qualified to participate
in more than one test, all segments are assigned at the same time. When
segments need to be set, a small javascript is injected into the <head>
via a call to CheezTest::run_user_segmentation(). This javascript
establishes the segment, sets a cookie to retain the segment, and reloads
the page.

Test case data (name, is_qualified, & group) are stored in the $active_tests
static hash and made accessible via the 'is_qualified_for', 'get_group_for', and
'is_in_group' static methods. This enables theme branching via:

if ( CheezTest::is_qualified_for( 'my-example-test' ) {
    //test-specific stuff goes here
}

- or -

if ( CheezTest::is_in_group( 'my-example-group' ) ) {
   //group-specific stuff goes here
}
