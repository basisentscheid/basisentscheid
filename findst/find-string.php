<?php
// Find-String
// Created by find-xss.net
// Author Reznik Vitaly
// Version 0.1.0
// 16.09.2011

if(session_id() == '') session_start();

$nick = "admin"; // Attention!!! Change it!
$password = "1234"; // Attention!!! Change it!

if(!isset($_SESSION['open']) && isset($_POST['nick']) && isset($_POST['password']) && $_POST['nick'] == $nick && $_POST['password'] == $password) {
	$_SESSION['open'] = 'yes';
}

if(isset($_POST['logout'])) {
	unset($_SESSION['open']);
}

class findString {

	var $invisibleFileNames;
	var $fileList;
	var $filesext;

	function __construct($path = "./") {
		$this->invisibleFileNames = array(".", "..", "find-string.php");
		$this->filesext = isset($_POST['files']) ? explode(",", str_replace(" ", "", $_POST['files'])) : array();
		$this->fileList = $this->scanDirectories($path);
	}

	function scanDirectories($rootDir, $allFiles = array()) {
		$dirContent = scandir($rootDir);
		foreach($dirContent as $key => $content) {
			$path = $rootDir.'/'.$content;
			$fileext = explode(".", $content);
			$fileext = $fileext[count($fileext)-1];
			if(!in_array($content, $this->invisibleFileNames) && (is_dir($path) || in_array($fileext, $this->filesext))) {
				$allFiles[] = $path;
				if(is_dir($path) && is_readable($path)) {
					$allFiles = $this->scanDirectories($path, $allFiles);
				}
			}
		}
		return $allFiles;
	}
}

$rootDir = isset($_POST['rootdir']) ? htmlentities($_POST['rootdir']) : dirname(__FILE__);
$findString = new findString($rootDir);
$i = 1;
$current = isset($_POST['current']) ? intval($_POST['current']) : 0;

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr">
	<head>
		<title>Find - String</title>
		<meta name="description" content="Find - Info module by http://find-xss.net" />
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	</head>
	<body>
		<div align="center">
			<b>Find-String</b>, powered by <b><a href="http://find-xss.net" >find-xss.net</a></b><br /><br />
			<?php if(isset($_SESSION['open'])): ?>
				<form action="" method="post">
					<br />
					<input type="submit" value="Logout" name="logout" />
				</form>
				<br />
				<form action="" method="post" id="inputform">
					<table>
						<tr>
							<td><b>Files for scan:</b></td>
							<td><input type="text" value="<?php echo isset($_POST['files']) ? htmlentities($_POST['files'], ENT_QUOTES, 'utf-8') : 'php, html, js, po, phtml';?>" name="files" size="80" /></td>
							<td></td>
							<td></td>
						</tr>
						<tr>
							<td><b>Input a directory for scan:</b></td>
							<td><input type="text" value="<?php echo htmlentities($rootDir, ENT_QUOTES, 'utf-8');?>" name="rootdir" size="80" /></td>
							<td></td>
							<td></td>
						</tr>
						<tr>
							<td><b>Input a string or regular expression:</b></td>
							<td><input type="text" value="<?php echo isset($_POST['string']) ? htmlentities($_POST['string'], ENT_QUOTES, 'utf-8') : '';?>" name="string" size="80" /></td>
							<td>
								<input type="radio" value="string" name="type" <?php echo (!isset($_POST['type']) || (isset($_POST['type']) && $_POST['type'] == 'string')) ? 'checked' : ''; ?> /> As string
								<input type="radio" value="regexp" name="type" <?php echo (isset($_POST['type']) && $_POST['type'] == 'regexp') ? 'checked' : ''; ?> /> As regular expression
							</td>
							<td><input type="hidden" value="0" name="current" id="current" /><input type="submit" value="Find" name="find" /></td>
						</tr>
					</table>
				</form>
				<br /><b>String found in:</b><br /><br />
				<table>
					<th>File name</th>
					<?php
						if(isset($_POST['type']) && $_POST['type'] != 'regexp') $_POST['string'] = "/".preg_quote($_POST['string'], '/')."/s";
						$found = 0;
						foreach($findString->fileList as $item):
							if(is_readable($item)):
								$contents = file_get_contents($item);
								if(isset($_POST['string']) && $_POST['string'] != ''):
									$res = @preg_match($_POST['string'], $contents, $match);
									if($res === FALSE) {
										echo "<h3>Wrong regular expression!</h3>";
										break;
									}
									if($res):
										$found++;
										if($found <= ($current + 50) && $found >= $current): ?>
											<tr style="background-color: #<?php echo $i > 0 ? "DDDDDD": "EEEEEE"; $i = 1 - $i;?>" >
												<td><?php echo htmlentities($item); ?></td>
											</tr>
										<?php endif; ?>
									<?php endif; ?>
								<?php endif; ?>
							<?php endif; ?>
					<?php if($found > ($current + 50)) break; endforeach; ?>
					<tr>
						<td align="center">
							<?php
								if(($current - 50) >= 0 || (!$found && $current))
									echo '<a onclick="document.getElementById(\'current\').value='.($current - 50).'; document.getElementById(\'inputform\').submit();" href="#">Preview</a>';
								echo ' Page '.($current/50+1);
								if($found > $current && $found > 49)
									echo ' <a onclick="document.getElementById(\'current\').value='.($current + 50).'; document.getElementById(\'inputform\').submit();" href="#">Next</a>';
							?>
						</td>
					</tr>
				</table>
				<br /><?php if(!$found) echo "Not Found";?>
			<?php else: ?>
				<form action="" method="post">
					<br />
					Nick &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="text" value="" name="nick" />
					<br />
					Password <input type="password" value="" name="password" />
					<br />
					<input type="submit" value="Login" name="login" />
				</form>
			<?php endif; ?>
			<br /><br />
			Copyright © 2010-2011 XSS Scanner http://find-xss.net
		</div>
	</body>
</html>
