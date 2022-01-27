  Feature: Automattic users are in the VIP Support Role
  As a site owner
  In order to distinguish support users
  I must clearly see who is an VIP Support user

  @javascript @insulated
  Scenario: The plugin is shown as active in Must Use plugins
    Given I am logged in as "admin" with the password "password" and I am on "/wp-admin/plugins.php?plugin_status=mustuse"
    Then I should see "WordPress.com VIP Support"

  @javascript @insulated
  Scenario: Adding an A8c user requires them to verify their email address
    Given I am logged in as "admin" with the password "password" and I am on "/wp-admin/user-new.php"
    Then I should see "Add New User"
    When I fill in "Username" with "actual_a8c_user"
    And I fill in "E-mail" with "actual_a8c_user@automattic.com"
    And I fill in "Password" with "password"
    And I fill in "Repeat Password" with "password"
    And I select "VIP Support" from "Role"
    And I press "Add New User"
    Then I should not see "This user was given the VIP Support role"
    And I should see "This user’s Automattic email address must be verified before they can be assigned the VIP Support role."
    When I follow "actual_a8c_user"
    Then I should see "Personal Options"
    Then I should see "email is not verified"

  @javascript @insulated
  Scenario: A8c users with unverified emails are not in the VIP Support role
    Given I am logged in as "admin" with the password "password" and I am on "/wp-admin/users.php?role=vip_support"
    Then I should see "Users"
    And I should not see "actual_a8c_user"

  @javascript @insulated
  Scenario: Trying to assign unverified A8c users to the VIP Support role fails and shows an error
    Given I am logged in as "admin" with the password "password" and I am on "/wp-admin/users.php?s=actual_a8c_user"
    And I follow "actual_a8c_user"
    And I select "VIP Support" from "Role"
    And I press "Update User"
    Then I should see "This user’s Automattic email address is not verified"
    And I am on "/wp-admin/users.php?role=vip_support"
    Then I should not see "actual_a8c_user"

  @javascript @insulated
  Scenario: Automattic users editing their profile do not receive unrequested verification emails
    Given I am logged in as "actual_a8c_user" with the password "password" and I am on "/wp-admin/profile.php"
    And I clear the email inbox
    And I fill in "First Name" with "Some name"
    And I press "Update Profile"
    Then I should not see an email to another@automattic.com

  @javascript @insulated
  Scenario: Unverified Automattic users can request a verification email
    Given I am logged in as "actual_a8c_user" with the password "password" and I am on "/wp-admin/profile.php"
    And I follow "re-send verification email"
    Then the latest email to actual_a8c_user@automattic.com should match "You need to verify your Automattic email address for your user on"

  @javascript @insulated
  Scenario: Following an incorrect verification link does not verify the user's email
    Given I am logged in as "actual_a8c_user" with the password "password" and I am on "/?vip_verify_code=invalid12345&vip_user_login=actual_a8c_user"
    Then I should not see "Your email has been verified"
    When I am logged in as "admin" with the password "password" and I am on "/wp-admin/users.php?role=vip_support"
    Then I should not see "actual_a8c_user"

  @javascript @insulated
  Scenario: Following the verification link in the email while logged in as another user does not verify the user's email
    Given I am logged in as "admin" with the password "password" and I am on "/wp-admin/"
    And I follow the second URL in the latest email to actual_a8c_user@automattic.com
    Then I should not see "Your email has been verified as actual_a8c_user@automattic.com"
    When I am on "/wp-admin/users.php?s=actual_a8c_user"
    And I follow "actual_a8c_user"
    Then I should see "This user’s Automattic email address is not verified"

  @javascript @insulated
  Scenario: Following the verification link in the email while logged in as that user verifies the user's email successfully
    Given I am logged in as "actual_a8c_user" with the password "password" and I am on "/wp-admin/"
    And I follow the second URL in the latest email to actual_a8c_user@automattic.com
    Then I should see "Your email has been verified as actual_a8c_user@automattic.com"
    When I am on "/wp-admin/profile.php"
    Then I should see "email is verified"

  @javascript @insulated
  Scenario: A8c users with verified email are in the VIP Support role
    Given I am logged in as "admin" with the password "password" and I am on "/wp-admin/users.php?role=vip_support"
    Then I should see "actual_a8c_user"

  @javascript @insulated
  Scenario: A8c users cannot be moved out of the VIP Support role
    Given I am logged in as "admin" with the password "password" and I am on "/wp-admin/users.php?role=vip_support"
    And I follow "actual_a8c_user"
    And I select "Contributor" from "Role"
    And I press "Update User"
    Then I should see "VIP Support users can only be assigned the VIP Support role, or deleted."
    When I am on "/wp-admin/users.php?role=vip_support"
    Then I should see "actual_a8c_user"

  @javascript @insulated
  Scenario: VIP Support users can do Administrator type things
    Given I am logged in as "actual_a8c_user" with the password "password" and I am on "/wp-admin/plugins.php"
    Then I should not see "You do not have sufficient permissions to access this page."
    And I should see "Plugins"
    When I am on "/wp-admin/users.php"
    Then I should not see "You do not have sufficient permissions to access this page."
    And I should see "Users"
    When I am on "/wp-admin/options-general.php"
    Then I should not see "You do not have sufficient permissions to access this page."
    And I should see "General Settings"

  @javascript @insulated
  Scenario: A8c users with verified email lose verified status if they change their email address
    Given I am logged in as "actual_a8c_user" with the password "password" and I am on "/wp-admin/profile.php"
    And I fill in "E-mail" with "actual_a8c_user_new@automattic.com"
    And I press "Update Profile"
    Then I should see "email is not verified"
    And I fill in "E-mail" with "actual_a8c_user@automattic.com"
    And I press "Update Profile"
    Then I should see "email is not verified"

  @javascript @insulated
  Scenario: New non-A8c users cannot be assigned the VIP Support role
    Given I am logged in as "admin" with the password "password" and I am on "/wp-admin/user-new.php"
    Then I should see "Add New User"
    When I fill in "Username" with "random_user"
    And I fill in "E-mail" with "random_user@example.invalid"
    And I fill in "Password" with "password"
    And I fill in "Repeat Password" with "password"
    And I select "VIP Support" from "Role"
    And I press "Add New User"
    Then I should see "Only Automattic staff can be assigned the VIP Support role"
    And I should not see "This user was given the VIP Support role"
    When I follow "random_user"
    Then I should see "Personal Options"
    And I should not see "email is not verified"
    And I should not see "email is verified"
    When I am on "/wp-admin/users.php?role=vip_support"
    Then I should not see "random_user"

  @javascript @insulated
  Scenario: Existing non-A8c users cannot be assigned the VIP Support role
    Given I am logged in as "admin" with the password "password" and I am on "/wp-admin/users.php?s=random_user"
    And I follow "random_user"
    And I select "VIP Support" from "Role"
    And I press "Update User"
    Then I should not see "This user’s Automattic email address is not verified"
    And I should see "Only users with a recognised Automattic email address can be assigned the VIP Support role."
    When I am on "/wp-admin/users.php?role=vip_support"
    Then I should not see "random_user"

  @javascript @insulated
  Scenario: Adding a subscriber user for the next test
    Given I am logged in as "admin" with the password "password" and I am on "/wp-admin/user-new.php"
    Then I should see "Add New User"
    When I fill in "Username" with "subscriber"
    And I fill in "E-mail" with "subscriber@example.invalid"
    And I fill in "Password" with "password"
    And I fill in "Repeat Password" with "password"
    And I select "Subscriber" from "Role"
    And I press "Add New User"

  @javascript @insulated
  Scenario: Subscriber users do not accidentally have elevated capabilities
    Given I am logged in as "subscriber" with the password "password" and I am on "/wp-admin/plugins.php"
    Then I should see "You do not have sufficient permissions to access this page."
    And I should not see "Plugins"
    When I am on "/wp-admin/users.php"
    Then I should see "Cheatin’ uh?"
    And I should not see "Users"
    When I am on "/wp-admin/options-general.php"
    Then I should see "You do not have sufficient permissions to access this page."
    And I should not see "General Settings"
