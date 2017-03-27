<?php

use Behat\Behat\Context\ClosuredContextInterface,
	Behat\Behat\Context\TranslatedContextInterface,
	Behat\Behat\Context\BehatContext,
	Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode,
	Behat\Gherkin\Node\TableNode;

use Behat\MinkExtension\Context\MinkContext;
use WebDriver\Exception\NoAlertOpenError;

if ( getenv('WORDPRESS_FAKE_MAIL_DIR') ) {
	define( 'WORDPRESS_FAKE_MAIL_DIR', getenv('WORDPRESS_FAKE_MAIL_DIR') );
} else {
	define( 'WORDPRESS_FAKE_MAIL_DIR', '/Users/simonwheatley/Vagrants/vvv/www/wordpress-default/mail/' );
}

/**
 * Features context.
 */
class FeatureContext extends MinkContext {

	/**
	 * @Then /echo debugging information/
	 */
	public function echoDebugging() {
		echo $this->getSession()->getDriver()->getCurrentUrl();
		echo $this->getSession()->getDriver()->getContent();
	}

	/**
	 * @Then /^I wait for ([\d]*) seconds$/
	 */
	public function iWaitForSeconds( $arg1 ) {
		sleep( intval( $arg1 ) );
	}

	/**
	 * @When /^I am logged in as "([^"]+)" with the password "([^"]+)" and I am on "([^"]+)"$/
	 */
	public function wpLogin( $username, $password, $redirect_to ) {
		$session = $this->getSession();
		$context = $this->getMainContext();
		$url = $context->locatePath( '/wp-login.php' );
		$url .= '?' . http_build_query( array( 'redirect_to' => $redirect_to ) );
		$session->visit( $url );
		$this->fillField( 'Username', $username );
		$this->fillField( 'Password', $password );
		$this->pressButton( 'Log In' );
	}

	/**
	 * Checks, that current page PATH matches regular expression.
	 *
	 * @Then /^the request URI should match (?P<pattern>"([^"]|\\")*")$/
	 */
	public function assertRequestUriRegExp ( $pattern ) {
		$session = $this->getSession();
		$regex = $this->fixStepArgument($pattern);
		$request_uri = parse_url( $session->getCurrentUrl(), PHP_URL_PATH );

		if (!preg_match($regex, $request_uri)) {
			$exception_message = sprintf('Current request URI "%s" does not match the regex "%s".', $request_uri, $regex);
			throw new ExpectationException($exception_message, $session);
		}
	}

	/**
	 * @Then /^the latest email to ([^ ]+@[^ ]+) should match "([^"]*)"$/
	 */
	public function assertFakeEmailReceipt( $email_address, $pattern ) {
		require_once( __DIR__ . '/fake-mail.php' );
		$regex = $this->fixStepArgument($pattern);
		$emails = a8c_vip_get_fake_mail_for( $email_address );
		if ( empty( $emails ) ) {
			$exception_message = sprintf( 'Did not find any emails to %s', $email_address );
			throw new \Behat\Mink\Exception\ExpectationException($exception_message, $this->getSession());
		}
		$message = a8c_vip_read_fake_mail( array_pop( $emails ) );
		$matched_regex = false;
		if ( preg_match("/$regex/", $message['subject']) ) {
			$matched_regex = true;
		}
		if ( preg_match("/$regex/", $message['body']) ) {
			$matched_regex = true;
		}

		if ( ! $matched_regex ) {
			$exception_message = sprintf( 'Did not find an email to %s which matched "%s"', $email_address, $pattern );
			throw new \Behat\Mink\Exception\ExpectationException($exception_message, $this->getSession());
		}
	}

	/**
	 * @Given /^I follow the (\w+) URL in the latest email to ([^ ]+@[^ ]+)$/
	 */
	public function followEmailUrl( $ordinal, $email ) {
		require_once( __DIR__ . '/fake-mail.php' );
		$emails = a8c_vip_get_fake_mail_for( $email );
		$message = a8c_vip_read_fake_mail( array_pop( $emails ) );
		// Nicked this regex from WordPress make_clickable
		preg_match_all('#\bhttps?://[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/))#', $message['body'], $matches);
		$links = $matches[0];
		$ordinals = array( 'first' => 1, 'second' => 2, 'third' => 3, 'fourth' => 4, 'fifth' => 5, 'sixth' => 6, 'seventh' => 7, 'eighth' => 8, 'ninth' => 9, 'tenth' => 10 );
		$ordinal = strtolower( $ordinal );
		if ( ! isset( $ordinals[$ordinal] ) ) {
			$message = sprintf( 'Could not identify ordinal "%s" (n.b. we only go up to "tenth")', $ordinal );
			throw new \Behat\Mink\Exception\ExpectationException($message, $this->getSession());
		}
		$i = $ordinals[$ordinal];
		// Our array is zero indexed
		$i--;
		if ( ! isset( $links[$i] ) ) {
			$exception_message = sprintf( 'Could not find a %s link amongst: %s', $ordinal, implode( ', ', $links ) );
			throw new \Behat\Mink\Exception\ExpectationException($exception_message, $this->getSession());
		}
//		throw new \Behat\Mink\Exception\ExpectationException($links[$i], $this->getSession());
		$this->getSession()->visit($links[$i]);

	}

	/**
	 * @Given /^I should not see an email to ([^ ]+@[^ ]+)$/
	 */
	public function assertNoEmailTo( $email_address ) {
		require_once( __DIR__ . '/fake-mail.php' );
		$emails = a8c_vip_get_fake_mail_for( $email_address );
		if ( ! empty( $emails ) ) {
			$message = a8c_vip_read_fake_mail( array_pop( $emails ) );
			$exception_message = sprintf( 'There should be no email to %s, but at least one was found the first had the subject: %s', $email_address, $message['subject'] );
			throw new \Behat\Mink\Exception\ExpectationException($exception_message, $this->getSession());
		}
	}

	/**
	 * @Then /^I clear the email inbox( for ([^ ]+@[^ ]+))?$/
	 */
	public function clearInbox( $specific = false, $email_address = '' ) {
		require_once( __DIR__ . '/fake-mail.php' );
		$specific = (bool) $specific;
		if ( ! $specific ) {
			a8c_vip_delete_fake_mail_for();
		} else {
			a8c_vip_delete_fake_mail_for( $email_address );
		}
	}
}