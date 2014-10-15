<?php
define("SALT", "");
define("IMAP", "localhost");
define("ADMIN_EMAIL", "admin@domain.tld");
define("LOGFILE", dirname( __FILE__) . DIRECTORY_SEPARATOR . "log.log");
define("SOAP_LOCATION", "https://remote/index.php");
define("SOAP_URI", "https://remote/");
?>
<html lang="fr">
<head>
	<meta charset="UTF-8">
	<title>Mail management</title>
	<style>
		html { font-family: serif; font-size: 14px; }
		form { background-color: #EEE; }
		p.collapse { display: none; }
		span.sameLine { display: inline-flex; }
	</style>
	<script src="jquery-1.11.1.min.js"></script>
	<script type="text/javascript">
		$(document).ready(function(){
			$('.collapseTrigger').click(function(){
				$('.collapse').slideToggle('slow');
				$(this).toggleClass('slideSign2');
				return false;
			});
		});

function toggleDiv(divid){
if(document.getElementById(divid).style.display == 'none'){
document.getElementById(divid).style.display = 'block';
}else{
document.getElementById(divid).style.display = 'none';
}
}

	</script>
</head>
<body>
<?php
if(!isset($_SESSION)) { session_start(); }
if(isset($_GET["logout"]) && $_GET["logout"] == "TRUE") { unset($_SESSION["password"]); unset($_SESSION["login"]); }

function login_form() {
?>
<form action="?" method="post" name="login">
	<b>Mail management login</b><br />
	Adresse email: <input type="text" name="login"><br />
	Mot de passe: <input type="password" name="password"><br />
	<input type="submit" value="Login">
</form>
<?php
}

function logger($message) {
	$content = $message . " from " . $_SERVER['REMOTE_ADDR'] . " at " . date("Y-m-d H:i:s") . "\n";
	if (is_writable(LOGFILE)) {
		if (!$handle = fopen(LOGFILE, 'a')) {
			mail(ADMIN_EMAIL, 'ERROR: Mail management', "Impossible d'ouvrir le fichier de log");
		}
		if (fwrite($handle, $content) === FALSE) {
			mail(ADMIN_EMAIL, 'ERROR: Mail management', "Impossible d'ecrire dans le fichier de log");
		}

		fclose($handle);
	} else {
		mail(ADMIN_EMAIL, 'ERROR: Mail management', "Le fichier de log n'est pas accessible en ecriture.");
	}
}

if(isset($_POST["login"])) { $_SESSION["login"] = $_POST["login"]; }
if(isset($_POST["password"])) { $_SESSION["password"] = $_POST["password"]; }
if(empty($_SESSION["login"]) OR empty($_SESSION["password"])) {
	echo login_form();
	exit;
}

if(isset($_POST["login"])) { $login = trim($_POST["login"]); } else { $login = trim($_SESSION["login"]); }
if(isset($_POST["password"])) { $password = trim($_POST["password"]); } else { $password = trim($_SESSION["password"]); }
// TODO: Fix error in apache log when user:pass is invalid
$imap = imap_open("{" .IMAP . ":993/imap/ssl/readonly/novalidate-cert}INBOX", $login, $password, NULL, 1);
if(!$imap) {
	unset($_SESSION["password"]);
	unset($_SESSION["login"]);
	logger("Failed login for user " . $login);
	echo "<b>Identifiant ou mot de passe incorrect.</b><br /><br />";
	echo login_form();
	exit;
} else {
	if(isset($_POST["login"])) { logger("Successful login for user " . $login); }
}

imap_close($imap);

$emailAndDomain = explode('@', $_SESSION["login"]);
$email = $emailAndDomain[0];
$domain = $emailAndDomain[1];
?>
(<?php echo $email . "<b>@" . $domain; ?></b>) <a href="?logout=TRUE"><small>Se d&eacute;connecter</small></a><br />
<br/>

<?php
$client = new SoapClient(null, array('location' => SOAP_LOCATION, 'uri' => SOAP_URI));
try {
	if($session_id = $client->login("", "")) {

		$domain_id = $client->mail_domain_get_by_domain($session_id,$domain);
		if(empty($domain_id)) {
			echo "Votre domaine pr&eacute;sente un probl&eagrave;me. Un message nous &agrave; &eacute;t&eacute; envoy&eacute;.";
			mail(ADMIN_EMAIL, 'ERROR: Mail management', 'Votre domaine pr&eacute;sente un probl&eagrave;me.');
			exit;
		}

		$domain_full = $client->mail_domain_get($session_id, $domain_id[0]["domain_id"]);

		$client_id = $client->client_get_id($session_id, $domain_full["sys_groupid"]);
		$server_id = $domain_full["server_id"];

// TODO: ADD POPUP FOR SAFETY & PREVENT!
// TODO: WHEN ACTION, SAY IS IN TEMPSTACK

		if(isset($_POST["action"])) {
			logger("Action: " . serialize(preg_replace("/[\n\r]/", "<br />", $_POST)) . " by user " . $login);

			$autoresponder_start_date = array('year' => substr($_POST["autoresponder_start_date"], 0, 4), 'month' => substr($_POST["autoresponder_start_date"], 5, 2), 'day' => substr($_POST["autoresponder_start_date"], 8, 2), 'hour' => substr($_POST["autoresponder_start_date"], 11, 2), 'minute' => substr($_POST["autoresponder_start_date"], 14, 2));
			$autoresponder_end_date = array('year' => substr($_POST["autoresponder_end_date"], 0, 4), 'month' => substr($_POST["autoresponder_end_date"], 5, 2), 'day' => substr($_POST["autoresponder_end_date"], 8, 2), 'hour' => substr($_POST["autoresponder_end_date"], 11, 2), 'minute' => substr($_POST["autoresponder_end_date"], 14, 2));

			switch($_POST["action"]) {
// TODO: ADD CONTROL, DO THING ONLY ON DOMAIN.
				case "addAccount":
					if(isset($_POST["autoresponder"]) && $_POST["autoresponder"] == "y") { $autoresponder = "y"; } else { $autoresponder = "n"; }
					$client->mail_user_add($session_id, $client_id, array('server_id' => $server_id, 'email' => trim($_POST["email"]) . "@" . $domain, 'login' => trim($_POST["email"]) . "@" . $domain, 'password' => $_POST["passwordAccount"], 'name' => $_POST["name"], 'uid' => 5000, 'gid' => 5000, 'maildir' => '/var/vmail/' . $domain . '/' . $_POST["email"], 'quota' => 10000000000, 'cc' => '', 'homedir' => '/var/vmail', 'autoresponder' => $autoresponder, 'autoresponder_start_date' => $autoresponder_start_date, 'autoresponder_end_date' => $autoresponder_end_date, 'autoresponder_subject' => $_POST["autoresponder_subject"], 'autoresponder_text' => $_POST["autoresponder_text"], 'move_junk' => 'y', 'custom_mailfilter' => '', 'postfix' => 'y', 'access' => 'n', 'disableimap' => 'n', 'disablepop3' => 'n', 'disabledeliver' => 'n', 'disablesmtp' => 'n'));
					break;
				
				case "updateAccount":
					if(isset($_POST["autoresponder"]) && $_POST["autoresponder"] == "y") { $autoresponder = "y"; } else { $autoresponder = "n"; }
					$params = array('server_id' => $server_id, 'email' => trim($_POST["email"]) . "@" . $domain, 'login' => trim($_POST["email"]) . "@" . $domain, 'name' => $_POST["name"], 'uid' => 5000, 'gid' => 5000, 'maildir' => '/var/vmail/' . $domain . '/' . $_POST["email"], 'quota' => 10000000000, 'cc' => '', 'homedir' => '/var/vmail', 'autoresponder' => $autoresponder, 'autoresponder_start_date' => $autoresponder_start_date, 'autoresponder_end_date' => $autoresponder_end_date, 'autoresponder_subject' => $_POST["autoresponder_subject"], 'autoresponder_text' => $_POST["autoresponder_text"], 'move_junk' => 'y', 'custom_mailfilter' => '', 'postfix' => 'y', 'access' => 'n', 'disableimap' => 'n', 'disablepop3' => 'n', 'disabledeliver' => 'n', 'disablesmtp' => 'n');
					if(isset($_POST["passwordAccount"]) && $_POST["passwordAccount"] != "") { $params["password"] = $_POST["passwordAccount"]; }
					$client->mail_user_update($session_id, $client_id, $_POST["id"], $params);
					break;
				
				case "deleteAccount":
					$client->mail_user_delete($session_id, $_POST["id"]);
					break;
				
				case "addCatchall":
					$client->mail_catchall_add($session_id, $client_id, array('server_id' => $server_id, 'source' =>  "@" . $domain, 'destination' => trim($_POST["destination"]), 'type' => 'catchall', 'active' => 'y'));
					break;
				
				case "updateCatchall":
					$client->mail_catchall_update($session_id, $client_id, $_POST["id"], array('server_id' => $server_id, 'source' => "@" . $domain, 'destination' => trim($_POST["destination"]), 'type' => 'catchall', 'active' => 'y'));
					break;
				
				case "deleteCatchall":
					$client->mail_catchall_delete($session_id, $_POST["id"]);
					break;

				case "addAlias":
// TODO: replace space by newline for destination
					$client->mail_forward_add($session_id, $client_id, array('server_id' => $server_id, 'source' => trim($_POST["source"]) . "@" . $domain, 'destination' => trim($_POST["destination"]), 'type' => 'forward', 'active' => 'y'));
					break;

				case "updateAlias":
					$client->mail_forward_update($session_id, $client_id, $primary_id, $params);
					break;

				case "deleteAlias":
					$client->mail_forward_delete($session_id, $_POST["id"]);
					break;
					
			}
		}

		$email_full = $client->mail_user_get($session_id, array('sys_groupid' => $domain_full["sys_groupid"]));
		$catchall = $client->mail_catchall_get($session_id, array('sys_groupid' => $domain_full["sys_groupid"], 'type' => 'catchall', 'source' => '@'.$domain));
		$forward_full = $client->mail_forward_get($session_id, array('sys_groupid' => $domain_full["sys_groupid"], 'type' => 'forward'));		
	}

	$client->logout($session_id);

} catch (SoapFault $e) {
	mail(ADMIN_EMAIL, 'ERROR: Mail management', 'SOAP Error: '.$e->getMessage());
	die('SOAP Error: '.$e->getMessage());
	echo "Please contact the server administator";
}
// TODO: show quota
// TODO: show docuementation for each section
?>
<h2>Comptes email:</h2>
<form action="?" method="post">
	<input type="hidden" name="action" value="addAccount">
	<input type="text" name="email" value="adresse" size="25">@<?php echo $domain; ?> <br />
	Nom et Pr&eacute;nom du compte / Mot de passe<br />
	<input type="text" name="name" value="Nom Pr&eacute;nom" size="25"> <input type="password" name="passwordAccount" value="password"> <input type="checkbox" name="autoresponder" onclick="javascript:toggleDiv('mydiv0');" value="y"> R&eacute;ponse automatique (absence de bureau)<br />
<?php
// TODO: add password 5char min
?>		
		<div id="mydiv0" style="display:none">
		Du <input type="text" name="autoresponder_start_date" value="<?php echo date("Y-m-d H:i"); ?>" size="21"> au <input type="text" name="autoresponder_end_date" value="<?php echo date("Y-m-d H:i", strtotime("+1 week")); ?>" size="21"><br />
		<input type="text" name="autoresponder_subject" value="Absence de bureau" size="32"><br />
		<textarea name="autoresponder_text" rows="7" cols="60">Bonjour, &#13;Je suis absent(e) du XXXX au XXXXX.&#13;En cas d'urgence, veuillez contacter XXXXXXX par t&eacute;l&eacute;phone au XX.XX.XX.XX.XX ou par email &agrave; l'adresse XXXXXX@<?php echo $domain; ?>.&#13;&#13;Cordialement,&#13;XXXXXX</textarea><br />
	</div>
	<input type="submit" name="submit" value="ajouter">
</form>
<?php
if(empty($email_full)) {
?>
Aucun
<?php
} else {
	foreach($email_full as $value) {
		$i = rand();
		$k = rand();

		$accountEmailAndDomain = explode('@', $value["email"]);
		$accountEmail = $accountEmailAndDomain[0];
		$accountDomain = $accountEmailAndDomain[1];
// TODO: alert when delete
// TODO: add password 5char min
?>
<span class="sameLine">
	<?php echo $value["email"]; ?> (<?php echo $value["name"]; ?>) 
	<form action="?" method="post"><input type="hidden" name="action" value="deleteAccount"><input type="hidden" name="id" value="<?php echo $value["mailuser_id"]; ?>"><input type="submit" value="supprimer"></form>
	<input type="button" value="editer" onclick="javascript:;" onmousedown="toggleDiv('mydiv<?php echo $i; ?>');">
	<div id="mydiv<?php echo $i; ?>" style="display:none">
		<form action="?" method="post"><input type="hidden" name="action" value="updateAccount"><input type="hidden" name="id" value="<?php echo $value["mailuser_id"]; ?>">
			<input type="text" name="email" value="<?php echo $accountEmail; ?>" size="25">@<?php echo $domain; ?> <br />
				Nom et Pr&eacute;nom du compte / Mot de passe <small>(laisser vide = actuel)</small><br />
				<input type="text" name="name" value="<?php echo $value["name"]; ?>" size="25"> <input type="password" name="passwordAccount" value=""> <input type="checkbox" name="autoresponder" onclick="javascript:toggleDiv('mydiv<?php echo $k; ?>');" value="y" <?php if(isset($value["autoresponder"]) && $value["autoresponder"] == "y") { echo "checked"; } ?>> R&eacute;ponse automatique (absence de bureau)<br />
			<div id="mydiv<?php echo $k; ?>" <?php if(!isset($value["autoresponder"]) OR $value["autoresponder"] != "y") { echo "style=\"display:none\""; } ?>>
				Du <input type="text" name="autoresponder_start_date" value="<?php echo $value["autoresponder_start_date"]; ?>" size="21"> au <input type="text" name="autoresponder_end_date" value="<?php echo $value["autoresponder_end_date"]; ?>" size="21"><br />
				<input type="text" name="autoresponder_subject" value="<?php echo $value["autoresponder_subject"]; ?>" size="32"><br />
				<textarea name="autoresponder_text" rows="7" cols="60"><?php echo $value["autoresponder_text"]; ?></textarea><br />
			</div>
			<input type="submit" value="modifier">
		</form><br />
	</div>
</span><br />
<?php
	}
}
?>
<h2>Email collecteur:</h2>
<?php
if(empty($catchall)) {
?>
Aucun
<form action="?" method="post">
	<input type="hidden" name="action" value="addCatchall">
	<input type="text" name="destination" value="adresse@<?php echo $domain; ?>" size="35"> <input type="submit" name="submit" value="ajouter">
</form>
<?php
} else {
        foreach($catchall as $value) {
		$i++;
?>
<span class="sameLine">
	<form action="?" method="post"><input type="hidden" name="action" value="updateCatchall"><input type="hidden" name="id" value="<?php echo $value["forwarding_id"]; ?>"><input type="text" name="destination" value="<?php echo $value["destination"]; ?>" size="32"><input type="submit" value="modifier"></form>
	<form action="?" method="post"><input type="hidden" name="action" value="deleteCatchall"><input type="hidden" name="id" value="<?php echo $value["forwarding_id"]; ?>"><input type="submit" value="supprimer"></small></form>
</span>

<?php
        }
}
?>
<h2>Alias d'email:</h2>
<form action="?" method="post">
	<input type="hidden" name="action" value="addAlias">
	<input type="text" name="source" value="adresse" size="25">@<?php echo $domain; ?> -> <br />
	<textarea name="destination" rows="5" cols="35">adresse2@<?php echo $domain; ?>&#13;adresse3@<?php echo $domain; ?></textarea> <br />
	<small>(un ligne par adresse)</small><br />
<input type="submit" name="submit" value="ajouter">
</form>
<?php
if(empty($forward_full)) {
?>
Aucun
<?php
} else {
	foreach($forward_full as $value) {
		$i= rand();
		$sourceEmailAndDomain = explode('@', $value["source"]);
		$sourceEmail = $sourceEmailAndDomain[0];
		$sourceDomain = $sourceEmailAndDomain[1];
?>
<span class="sameLine">
	<?php echo $value["source"]; ?> -> <?php echo $value["destination"]; ?> 
	<form action="?" method="post"><input type="hidden" name="action" value="deleteAlias"><input type="hidden" name="id" value="<?php echo $value["forwarding_id"]; ?>"><input type="submit" value="supprimer"></small></form> 
	<input type="button" value="editer" onclick="javascript:;" onmousedown="toggleDiv('mydiv<?php echo $i; ?>');">
	<div id="mydiv<?php echo $i; ?>" style="display:none">
		<form action="?" method="post"><input type="hidden" name="action" value="updateAlias"><input type="hidden" name="id" value="<?php echo $value["forwarding_id"]; ?>">
		<input type="text" name="source" value="<?php echo $sourceEmail; ?>" size="25">@<?php echo $sourceDomain; ?> -> <br />
		<textarea name="destination" rows="5" cols="35"><?php echo $value["destination"]; ?> </textarea> <br /><input type="submit" value="modifier"></small></form>
	</div>
</span><br />
<?php
	}
}
?>
</body>
</html>
