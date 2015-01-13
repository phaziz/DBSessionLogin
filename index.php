<?php

    /*
    ***************************************************************************
        DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
        Version 1, January 2015
        Copyright (C) 2015 Christian Becher | phaziz.com <christian@phaziz.com>
        Everyone is permitted to copy and distribute verbatim or modified
        copies of this license document, and changing it is allowed as long
        as the name is changed.
        DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
        TERMS AND CONDITIONS FOR COPYING, DISTRIBUTION AND MODIFICATION
        0. YOU JUST DO WHAT THE FUCK YOU WANT TO!
        +++ Visit http://phaziz.com +++
    ***************************************************************************
    */

	/*
		CREATE DATABASE IF NOT EXISTS `session_login` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
		USE `session_login`;
		
		DROP TABLE IF EXISTS `benutzer`;
		CREATE TABLE IF NOT EXISTS `benutzer` (
		  `id` int(255) NOT NULL AUTO_INCREMENT,
		  `username` varchar(50) NOT NULL,
		  `password` varchar(255) NOT NULL,
		  `hash` varchar(255) NOT NULL,
		  `last_login` int(10) NOT NULL DEFAULT '0',
		  `session` varchar(255) NOT NULL,
		  `status` int(1) NOT NULL DEFAULT '0',
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `id` (`id`)
		) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=2 ;
		
		INSERT INTO `benutzer` (`id`, `username`, `password`, `hash`, `last_login`, `session`, `status`) VALUES
		(1, 'phaziz', '$2y$06$zJ7C9cDoAys3zda61KdA1uDLUoZIfbjQdnKRhQXQIS5Jb99ewWPqe', 'ÌžÂõÀè+7ÍÖºÔ§@×Ù\Z5½Z*', 1421158275, '276665361421158269', 0); 
	*/
	
	/*
		Benutzername: phaziz
		Passwort: password77
	*/

	error_reporting(-1);
	session_start();

	if(!isset($_SESSION['logged_in']))
	{
		$_SESSION['logged_in'] = false;
	}

	class Utility
	{
		function timeElapsed($TIME)
		{
		    $BIT = array
		    (
		        ' Jahre(n)' => $TIME / 31556926 % 12,
		        ' Woche(n)' => $TIME / 604800 % 52,
		        ' Tage(n)' => $TIME / 86400 % 7,
		        ' Stunde(n)' => $TIME / 3600 % 24,
		        ' Minute(n)' => $TIME / 60 % 60,
		        ' Sekunde(n)' => $TIME % 60
	        );

		    foreach($BIT as $K => $V)
		    {
		        if($V > 0)
		        {
		        	$RET[] = $V . $K  . ' und ';
		        }
		    }

		    $RET = join(' ', $RET);
			$RET .= 'ENDE';
			$RET = str_replace(' und ENDE','!', $RET);

			if($RET != 'ENDE')
			{
				return $RET;
			}
			else
			{
				return 'unbekannt!';
			}
	    }

		public function randCode()
		{
			return mt_rand() . time();
		}

		public function encode($PASSWORD,$SALT,$COST = 6)
		{
			return password_hash($PASSWORD, PASSWORD_DEFAULT,
				array
				(
					'cost' => $COST,
					'salt' => $SALT
				)
			);
		}

		public function verify($PASSWORD,$HASHED)
		{
			if(password_verify($PASSWORD,$HASHED))
			{
				return true;
			}
			else
			{
				return false;
			}
		}
		
		public function returnUser($SESSION,$LAST_LOGIN,$DBCON)
		{
			$V_CODE = filter_var($_SESSION['session'],FILTER_SANITIZE_NUMBER_INT);
			$V_LAST_LOGIN = filter_var($_SESSION['last_login'],FILTER_SANITIZE_NUMBER_INT);
	        $QUERY = $DBCON -> prepare('SELECT * FROM benutzer WHERE session = :SESSION AND last_login = :LAST_LOGIN LIMIT 1;');
	        $QUERY -> execute(array(':SESSION' => $SESSION,':LAST_LOGIN' => (int) $V_LAST_LOGIN));
	        $USER = $QUERY -> fetch();

			if($USER)
			{
				return $USER;
			}
			else
			{
				return false;
			}
		}
	}

	$E = new Utility();
	$CODE = $E -> randCode();

    try
    {
        $DBCON = new PDO('mysql:host=localhost;dbname=session_login','root','root',array(PDO::ATTR_PERSISTENT => true));
        $DBCON -> setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    }
    catch (PDOException $e)
    {
		die('DB-ERROR: ' . __FILE__ . ' | ' . $e -> getMessage());
    }

	$USER = $E -> returnUser($_SESSION['session'],$_SESSION['last_login'],$DBCON);
	$USERNAME = $USER['username']; 

	if($_POST['try_login'] && $_POST['try_login'] == 'try_login' && $_POST['form_code'] != '' && is_numeric($_POST['form_code']) && $_POST['username'] != '' && $_POST['password'] != '')
	{
		$V_CODE = filter_var($_POST['form_code'],FILTER_SANITIZE_NUMBER_INT);
		$V_USERNAME = filter_var($_POST['username'],FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$V_PASSWORD = filter_var($_POST['password'],FILTER_SANITIZE_FULL_SPECIAL_CHARS);

		if($V_CODE != '' && $V_USERNAME != '' && $V_PASSWORD != '')
		{
            $QUERY = $DBCON -> prepare('SELECT * FROM benutzer WHERE username = :USERNAME AND status = :STATUS LIMIT 1;');
            $QUERY -> execute(array(':USERNAME' => $V_USERNAME,':STATUS' => (int) 0));
            $COUNTR = $QUERY -> rowCount();
            $USER = $QUERY -> fetch();
            $LAST_LOGIN = date('Y-m-d H:i:s');

			if($COUNTR && $COUNTR == 1)
			{
				$E = new Utility();
				$HASHED_PASSWORD = $E -> encode($V_PASSWORD,$USER['hash']);
				$USER_ID = $USER['id'];
				$TIME = time();

				if($HASHED_PASSWORD == $USER['password'])
				{
					try
                    {
                        $QUERY = 'UPDATE benutzer SET last_login = :LAST_LOGIN, session = :SESSION WHERE id = :USER_ID LIMIT 1;';
                        $STMT = $DBCON -> prepare($QUERY);
                        $STMT -> bindParam(':SESSION',$V_CODE,PDO::PARAM_INT);
                        $STMT -> bindParam(':LAST_LOGIN',$TIME,PDO::PARAM_INT);
						$STMT -> bindParam(':USER_ID',$USER_ID,PDO::PARAM_INT);
                        $STMT -> execute();

						$_SESSION['last_login'] = $TIME;
						$_SESSION['session'] = $V_CODE;
						$_SESSION['logged_in'] = true;

						header('Location: ?logged_in=true');
                    }
                    catch(PDOException $e)
                    {
						die('DB-ERROR: ' . __FILE__ . ' | ' . $e -> getMessage());
                    }
				}
				else
				{
					header('Location: ?user_not_found=true');
				}
			}
			else
			{
				header('Location: ?user_not_found=true');
			}
		}
		else
		{
			header('Location: ?incomplete_userdate=true');
		}
	}

	if($_POST['try_logout'] && $_POST['try_logout'] == 'try_logout')
	{
	    session_destroy();
		session_regenerate_id(true);
		$_SESSION['logged_in'] = false;
		header('Location: ?logged_out=true');
	}

?>
<!doctype html>
<html class="no-js" lang="de">
	    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
		<?php
		
			if($_SESSION['logged_in'] == true)
			{
				?>
					<title>Sie sind angemeldet!</title>
				<?php
			}
			else
			{
				?>
					<title>Bitte anmelden!</title>
				<?php
			}

		?>
        <link rel="stylesheet" href="css/foundation.min.css">
        <link rel="stylesheet" href="icons/foundation-icons.css">
        <script src="js/vendor/modernizr.js"></script>
        <style>
			.margin-top-25{margin-top: 25px;}
        </style>
    </head>
    <body>

		<?php

			if($_SESSION['logged_in'] == true)
			{
				if($_GET['logged_in'] && $_GET['logged_in'] == 'true')
				{
					?>
						<div data-alert class="alert-box success radius text-center">
							<strong>Sie sind jetzt angemeldet!</strong>
							<a href="#" class="close">&times;</a>
						</div>
					<?php
				}

					?>

					<div class="row margin-top-25">
						<div class="small-12 large-12 columns">
							<form name="logout" id="logout" action="" method="post" enctype="application/x-www-form-urlencoded" onkeydown="javascript:lK();">
								<div class="row collapse">
									<div class="small-12 large-12 columns">
										<input type="hidden" name="try_logout" id="try_logout" value="try_logout">
										<a href="" id="logoutr" name="logoutr" role="button" aria-label="submit form" class="button success postfix"><?php echo $USER['username']; ?> Abmelden</a>
									</div>
								</div>
							</form>
						</div>
					</div>

					<div class="row margin-top-25">
						<div class="small-12 large-12 columns">
							<p>Secret ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.</p>
						</div>
					</div>

					<div class="row margin-top-25">
						<div class="small-12 large-12 columns">
							<?php

								$nowtime = time();
								$oldtime = 1335939007;

								echo '<p><small>Angemeldet seit ' . $E -> timeElapsed($nowtime - $USER['last_login']) . ' <a href="#" id="timr"><span data-tooltip aria-haspopup="true" class="has-tip" title="Zeitangabe aktualisieren"><i class="fi-loop"></i></span></a></small></p>';

							?>
						</div>
					</div>
				<?php
			}
			else
			{
				
				if($_GET['logged_out'] && $_GET['logged_out'] == 'true')
				{
					?>
						<div data-alert class="alert-box success radius text-center">
						  <strong>Sie sind jetzt abgemeldet!</strong>
						  <a href="#" class="close">&times;</a>
						</div>
					<?php
				}

				if($_GET['user_not_found'] && $_GET['user_not_found'] == 'true')
				{
					?>
						<div data-alert class="alert-box alert radius text-center">
						  <strong>Benutzer ist nicht vorhanden!</strong>
						  <a href="#" class="close">&times;</a>
						</div>
					<?php
				}

				?>
					<div class="row margin-top-25">
						<div class="small-12 large-12 columns">
							<form name="login" id="login" action="" method="post" enctype="application/x-www-form-urlencoded" onkeydown="javascript:eK();">
								<input type="hidden" name="try_login" id="try_login" value="try_login">
								<input type="hidden" name="form_code" id="form_code" value="<?php echo $CODE; ?>">
								<div class="row collapse">
									<div class="small-2 large-2 columns">
										<span class="prefix">Anmeldung:</span>
									</div>
									<div class="small-4 large-4 columns">
										<input type="text" name="username" id="username" value="" placeholder="Benutzername">
									</div>
									<div class="small-4 large-4 columns">
										<input type="password" name="password" id="password" value="" placeholder="Passwort">
									</div>
									<div class="small-2 large-2 columns">
										<a href="" id="submitr" name="submitr" role="button" aria-label="submit form" class="button success postfix">Anmelden</a>
									</div>
								</div>
							</form>
						</div>
					</div>
				<?php

			}

		?>

        <script src="js/vendor/jquery.js"></script>
        <script src="js/foundation.min.js"></script>
        <script>
	        $(function()
		        {
		        	$(document).foundation();

					Foundation.utils.S('#username').focus();

					eK = function()
					{  
						if(event.keyCode == 13)
						{
							var U = Foundation.utils.S('#username').val();
		        			var P = Foundation.utils.S('#password').val();

		        			if(U == '' || P == '')
		        			{
		        				Foundation.utils.S('#username').focus();
		        				alert('Bitte Benutzerdaten angeben!');
		        				return false;
		        			}

		        			Foundation.utils.S('#login').submit();
		        			return false;
						}  
					}

					lK = function()
					{  
						if(event.keyCode == 13)
						{
		        			Foundation.utils.S('#logout').submit();
		        			return false;
						}  
					}

		        	Foundation.utils.S('#submitr').bind('click', function()
			        	{
		        			var U = Foundation.utils.S('#username').val();
		        			var P = Foundation.utils.S('#password').val();
		        			
		        			if(U == '' || P == '')
		        			{
		        				Foundation.utils.S('#username').focus();
		        				alert('Bitte Benutzerdaten angeben!');
		        				return false;
		        			}
		        			
		        			Foundation.utils.S('#login').submit();
		        			return false;
						}
					);

		        	Foundation.utils.S('#logoutr').bind('click', function()
			        	{
		        			Foundation.utils.S('#logout').submit();
		        			return false;
						}
					);

		        	Foundation.utils.S('.alert-box,#timr').bind('click', function()
			        	{
			        		location.href = './';
		        			return false;
						}
					);
		        }
	        );

        </script>
    </body>
</html>