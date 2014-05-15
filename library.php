<?php


/**
 * Updates the Swift Mailer settings.
 *
 * @param array $info
 * @return array [0] T/F<br />
 *               [1] Success / error message
 */
function swift_update_settings($info)
{
  global $L;

  $settings = array(
    "swiftmailer_enabled"     => $info["swiftmailer_enabled"],
    "smtp_server"             => $info["smtp_server"],
    "port"                    => $info["port"],
    "requires_authentication" => $info["requires_authentication"]
      );

  if (isset($info["username"]))
    $settings["username"] = $info["username"];
  if (isset($info["password"]))
    $settings["password"] = $info["password"];
  if (isset($info["authentication_procedure"]))
    $settings["authentication_procedure"] = $info["authentication_procedure"];

  ft_set_module_settings($settings);

  return array(true, $L["notify_settings_updated"]);
}


/**
 * Called on the test tab. It sends one of the three test emails: plain text, HTML and multi-part
 * using the SMTP settings configured on the settings tab. This is NOT for the test email done on the
 * email templates "Test" tab; it uses the main swift_send_email function for that.
 *
 * @param array $info
 * @return array [0] T/F<br />
 *               [1] Success / error message
 */
function swift_send_test_email($info)
{
  global $L;

  // find out what version of PHP we're running
  $version = phpversion();
  $version_parts = explode(".", $version);
  $main_version = $version_parts[0];
  $current_folder = dirname(__FILE__);
	
  if ($main_version == "5")
    $php_version_folder = "php5";
	else if ($main_version == "4")
    $php_version_folder = "php4";
  else
    return array(false, $L["notify_php_version_not_found_or_invalid"]);

  require_once("$current_folder/$php_version_folder/ft_library.php");
  require_once("$current_folder/$php_version_folder/Swift.php");
  require_once("$current_folder/$php_version_folder/Swift/Connection/SMTP.php");

  $settings = ft_get_module_settings();

  // if we're requiring authentication, include the appropriate authenticator file
  if ($settings["requires_authentication"] == "yes")
  {
    switch ($settings["authentication_procedure"])
    {
      case "LOGIN":
        require_once("$current_folder/$php_version_folder/Swift/Authenticator/LOGIN.php");
        break;
      case "PLAIN":
        require_once("$current_folder/$php_version_folder/Swift/Authenticator/PLAIN.php");
        break;
      case "CRAMMD5":
        require_once("$current_folder/$php_version_folder/Swift/Authenticator/CRAMMD5.php");
        break;
    }
  }

	// this passes off the control flow to the swift_php_ver_send_test_email() function
	// which is defined in both the PHP 5 and PHP 4 ft_library.php file, but only one of 
	// which was require()'d 
	return swift_php_ver_send_test_email($settings, $info);
}


/**
 * Sends an email with the Swift Mailer module.
 *
 * @param array $email_components
 * @return array
 */
function swift_send_email($email_components)
{
  // find out what version of PHP we're running
  $version = phpversion();
  $version_parts = explode(".", $version);
  $main_version = $version_parts[0];

  if ($main_version == "5")
    $php_version_folder = "php5";
  else if ($main_version == "4")
    $php_version_folder = "php4";
  else
    return array(false, $L["notify_php_version_not_found_or_invalid"]);

  // include the main files
  $current_folder = dirname(__FILE__);
  require_once("$current_folder/$php_version_folder/Swift.php");
  require_once("$current_folder/$php_version_folder/Swift/Connection/SMTP.php");

  $settings = ft_get_module_settings("", "swift_mailer");

  // if we're requiring authentication, include the appropriate authenticator file
  if ($settings["requires_authentication"] == "yes")
  {
    switch ($settings["authentication_procedure"])
    {
      case "LOGIN":
        require_once("$current_folder/$php_version_folder/Swift/Authenticator/LOGIN.php");
        break;
      case "PLAIN":
        require_once("$current_folder/$php_version_folder/Swift/Authenticator/PLAIN.php");
        break;
      case "CRAMMD5":
        require_once("$current_folder/$php_version_folder/Swift/Authenticator/CRAMMD5.php");
        break;
    }
  }

  $smtp_server = $settings["smtp_server"];
  $port        = $settings["port"];

  $success = true;
  $message = "The email was successfully sent.";

  if (empty($port))
    $smtp =& new Swift_Connection_SMTP($smtp_server);
  else
    $smtp =& new Swift_Connection_SMTP($smtp_server, $port);

  if ($settings["requires_authentication"] == "yes")
  {
    $smtp->setUsername($settings["username"]);
    $smtp->setPassword($settings["password"]);
  }

  $swift =& new Swift($smtp);

  // now send the appropriate email
  if (!empty($email_components["text_content"]) && !empty($email_components["html_content"]))
  {
    $email =& new Swift_Message($email_components["subject"]);
    $email->attach(new Swift_Message_Part($email_components["text_content"]));
    $email->attach(new Swift_Message_Part($email_components["html_content"], "text/html"));
  }
  else if (!empty($email_components["text_content"]))
	{
    $email =& new Swift_Message($email_components["subject"]);
		$email->attach(new Swift_Message_Part($email_components["text_content"]));
	}
  else if (!empty($email_components["html_content"]))
	{
    $email =& new Swift_Message($email_components["subject"]);
		$email->attach(new Swift_Message_Part($email_components["html_content"], "text/html"));
	}
	
	
  // now compile the recipient list
  $recipients =& new Swift_RecipientList();

  foreach ($email_components["to"] as $to)
  {
    if (!empty($to["name"]) && !empty($to["email"]))
      $recipients->addTo($to["email"], $to["name"]);
    else if (!empty($to["email"]))
      $recipients->addTo($to["email"]);
  }

  if (!empty($email_components["cc"]) && is_array($email_components["cc"]))
  {
    foreach ($email_components["cc"] as $cc)
    {
      if (!empty($cc["name"]) && !empty($cc["email"]))
        $recipients->addCc($cc["email"], $cc["name"]);
      else if (!empty($cc["email"]))
        $recipients->addCc($cc["email"]);
    }
  }

  if (!empty($email_components["bcc"]) && is_array($email_components["bcc"]))
  {
    foreach ($email_components["bcc"] as $bcc)
    {
      if (!empty($bcc["name"]) && !empty($bcc["email"]))
        $recipients->addBcc($bcc["email"], $bcc["name"]);
      else if (!empty($bcc["email"]))
        $recipients->addBcc($bcc["email"]);
    }
  }

  if (!empty($email_components["reply_to"]["name"]) && !empty($email_components["reply_to"]["email"]))
    $email->setReplyTo($email_components["reply_to"]["name"] . "<" . $email_components["reply_to"]["email"] . ">");
  else if (!empty($email_components["reply_to"]["email"]))
    $email->setReplyTo($email_components["reply_to"]["email"]);

  if (!empty($email_components["from"]["name"]) && !empty($email_components["from"]["email"]))
    $from =	new Swift_Address($email_components["from"]["email"], $email_components["from"]["name"]);
  else if (!empty($email_components["from"]["email"]))
    $from =	new Swift_Address($email_components["from"]["email"]);

  // finally, if there are any attachments, attach 'em
	if (isset($email_components["attachments"]))
	{
    foreach ($email_components["attachments"] as $attachment_info)
    {
      $filename      = $attachment_info["filename"];
      $file_and_path = $attachment_info["file_and_path"];

			if (!empty($attachment_info["mimetype"]))
        $email->attach(new Swift_Message_Attachment(new Swift_File($file_and_path), $filename, $attachment_info["mimetype"]));
			else
  			$email->attach(new Swift_Message_Attachment(new Swift_File($file_and_path), $filename));
    }
  }

  $swift->send($email, $recipients, $from);

  return array($success, $message);
}


/**
 * The Export Manager installation function. This is automatically called by Form Tools on installation.
 */
function swift_mailer__install($module_id)
{
  global $g_table_prefix;

  $queries[] = "
    INSERT INTO {$g_table_prefix}settings (setting_name, setting_value, module)
    VALUES (
      ";

  foreach ($queries as $query)
  {
    $result = mysql_query($query);
  }

  return array(true, "");
}


/**
 * The Swift Mailer uninstall script. This is called by Form Tools when the user explicitly chooses to
 * uninstall the module.
 */
function swift_mailer__uninstall($module_id)
{
  global $g_table_prefix;

  return array(true, "");
}