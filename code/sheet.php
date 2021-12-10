<?php

if( file_exists('install.php') )
{
	header("Location: install.php");
	exit;
}

$games_location = 'games/';
$game = $_GET['p'];

// Language logic

include 'lang/TranslateTool.php';
$language = TranslateTool::loadLanguage(isset($_GET['l']) ? $_GET['l'] : null, 'sheet.php');
$languageQuery = ($language != TranslateTool::getDefaultLanguage() ? '?l='. $language : '');

if (file_exists($games_location.$game.'/data-'. $language .'.xml'))
	$xml = simplexml_load_file($games_location.$game.'/data-'. $language .'.xml');
else if (file_exists($games_location.$game.'/data.xml'))
	$xml = simplexml_load_file($games_location.$game.'/data.xml');

if( !isset($xml) )
{
	if( $game == "credits" )
	{
		echo '<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		
		<title>Thanks!</title>
		<link href="https://cdnjs.cloudflare.com/ajax/libs/uikit/1.2.0/css/uikit.gradient.min.css" rel="stylesheet" type="text/css">
		<link href="style.css" rel="stylesheet" type="text/css">
	</head>

	<body>
		<div class="uk-container uk-container-center">
			<div class="uk-grid">
			</div>
		</div>
		<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
		<script type="text/javascript">
			$(function() {
				$(".uk-grid").load("credits.php");
			});
		</script>
	</body>
</html>';
		exit;		
	}
	else if( is_dir($games_location.$game) && $game != "lang" && $game != "images" && $game != "trailers" && $game != "_template" )
	{
		echo '<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		
		<title>Instructions</title>
		<link href="https://cdnjs.cloudflare.com/ajax/libs/uikit/1.2.0/css/uikit.gradient.min.css" rel="stylesheet" type="text/css">
		<link href="style.css" rel="stylesheet" type="text/css">
	</head>

	<body>
		<div class="uk-container uk-container-center">
			<div class="uk-grid">
			</div>
		</div>
		<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
	</body>
</html>';

		// Todo: These steps will fail if safemode is turned on
		if( !is_dir($games_location.$game.'/images') ) {
			mkdir($games_location.$game.'/images');
		}
		if( !is_dir($games_location.$game.'/trailers') ) {
			mkdir($games_location.$game.'/trailers');
		}
		if( !file_exists($games_location.$game.'/_data.xml') ) {
			copy('_template/_data.xml', $games_location.$game.'/_data.xml');
		}

		exit;
	}
	else
	{
		header("Location: index.php");
		exit;
	}
}

/* check for distribute() keyfile */
$files = glob($games_location.$game.'/ds_*');
foreach( $files as $keyfile ) {
	$keyfileContent = fopen($keyfile, 'r');
	$presskitURL = fgets($keyfileContent);
	$url = fgets($keyfileContent);
	$key = substr($keyfile, strpos($keyfile,'/ds_') + 4);
	$data = array('key' => $key, 'url' => $url);
	fclose($keyfileContent);

	if( function_exists('curl_version') ) {
		// curl exists. this is good. let's use it.
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$result = curl_exec($ch);
		if( $result != "FAIL" ) $press_request = TRUE;
		else {
			$press_request_fail = TRUE;
			$press_request_fail_msg = tl('There was an unexpected error retrieving data from distribute(). Please try again later.');			
		}

		curl_close($ch);
	}
	else if( ini_get('allow_url') ) {
		// well maybe this is a good fallback who knows?
		$options = array(
			'http' => array(
				'header' => 'Content-type: application/x-www-form-urlencoded',
				'method' => 'POST',
				'content' => http_build_query($data),
			),
		);

		$context = stream_context_create($options);
		$result = file_get_contents($url);
		if( $result != "FAIL" ) $press_request = TRUE;
		else {
			$press_request_fail = TRUE;
			$press_request_fail_msg = tl('There was an unforeseen error retrieving data from distribute(). Please try again later.');			
		}
	} else {
		// it doesn't matter you have a keyfile, you can't integrate
		$press_request = FALSE;
		$press_request_fail = TRUE;
		$press_request_fail_msg = tl('There is no method to communicate with distribute() available on your server. This functionality is not currently available. Remove the keyfile to remove this warning.');
	}
}

// Set default value for monetize
$monetize = 0;

foreach( $xml->children() as $child )
{
	switch( $child->getName() )
	{
		case("title"):
			define("GAME_TITLE", $child);
			break;	
		case("release-date"):
			define("GAME_DATE", $child);
			break;
		case("website"):
			define("GAME_WEBSITE", $child);
			break;
		case("platforms"):
			$platforms = array();
			$i = 0;
			foreach( $child->children() as $subchild )
			{
				$platforms[$i][$subchild->getName()] = $subchild;
				$i++;
			}
			break;
		case("prices"):
			$prices = array();
			$i = 0;
			foreach( $child->children() as $subchild )
			{
				$prices[$i][$subchild->getName()] = $subchild;
				$i++;
			}
			break;
		case("description"):
			define("GAME_DESCRIPTION", $child);
			break;
		case("history"):
			define("GAME_HISTORY", $child);
			break;
		case("histories"):
			$histories = array();
			$i = 0;
			foreach( $child->children() as $subchild )
			{
				$histories[$i][$subchild->getName()] = $subchild;
				$i++;
			}
			break;
		case("features"):
			$features = array();
			$i = 0;
			foreach( $child->children() as $subchild )
			{
				$features[$i] = $subchild;
				$i++;
			}
			break;	
		case("trailers"):
			$trailers = array();
			$i = 0;
			foreach( $child->children() as $subchild )
			{
				$trailers[$i][$subchild->getName()] = $subchild;
				$i++;
			}
			break;					
		case("awards"):
			$awards = array();
			$i = 0;
			foreach( $child->children() as $subchild )
			{
				$awards[$i][$subchild->getName()] = $subchild;
				$i++;
			}
			break;					
		case("quotes"):
			$quotes = array();
			$i = 0;
			foreach( $child->children() as $subchild )
			{
				$quotes[$i][$subchild->getName()] = $subchild;
				$i++;
			}
			break;					
		case("press-can-request-copy"):
			if( strtolower($child) != "false" ) $press_request_outdated_warning = TRUE;
			break;
		case("monetization-permission"):
			if( strtolower($child) == "false" ) $monetize = 1;
			else if( strtolower($child) == "ask") $monetize = 2;
			else if( strtolower($child) == "non-commercial") $monetize = 3;
			else if( strtolower($child) == "monetize") $monetize = 4;
			break;
		case("additionals"):
			$additionals = array();
			$i = 0;
			foreach( $child->children() as $subchild )
			{
				$additionals[$i][$subchild->getName()] = $subchild;
				$i++;
			}
			break;					
		case("credits"):
			$credits = array();
			$i = 0;
			foreach( $child->children() as $subchild )
			{
				$credits[$i][$subchild->getName()] = $subchild;
				$i++;
			}
			break;					
	}
}

if (file_exists('data-'. $language .'.xml'))
	$xml = simplexml_load_file('data-'. $language .'.xml');
else
	$xml = simplexml_load_file('data.xml');

foreach( $xml->children() as $child )
{
	switch( $child->getName() )
	{
		case("title"):
			define("COMPANY_TITLE", $child);
			break;	
		case("based-in"):
			define("COMPANY_BASED", $child);
			break;
		case("description"):
			define("COMPANY_DESCRIPTION", $child);
			break;
		case("analytics"):
			define("ANALYTICS", $child);
			break;
		case("contacts"):
			$contacts = array();
			$i = 0;
			foreach( $child->children() as $subchild )
			{
				$contacts[$i][$subchild->getName()] = $subchild;
				$i++;
			}
			break;					
	}
}

function parseLink($uri)
{
    $parsed = trim($uri);
    if( strpos($parsed, "http://") === 0 )
        $parsed = substr($parsed, 7);
    if (strpos($parsed, "https://") === 0 )
        $parsed = substr($parsed, 8);
    if( strpos($parsed, "www.") === 0 )
        $parsed = substr($parsed, 4);
    if( strrpos($parsed, "/") == strlen($parsed) - 1)
        $parsed = substr($parsed, 0, strlen($parsed) - 1);
    if( substr($parsed,-1,1) == "/" )
    	$parsed = substr($parsed, 0, strlen($parsed) - 1);

    return $parsed;
}

echo '<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		
		<title>'. COMPANY_TITLE .'</title>
		<link href="https://cdnjs.cloudflare.com/ajax/libs/uikit/1.2.0/css/uikit.gradient.min.css" rel="stylesheet" type="text/css">
		<link href="style.css" rel="stylesheet" type="text/css">
	</head>

	<body>
		<div class="uk-container uk-container-center">
			<div class="uk-grid">
				<div id="navigation" class="uk-width-medium-1-4">
					<h1 class="nav-header">'. COMPANY_TITLE .'</h1>
					<a class="nav-header" href="'. $languageQuery .'" target="_self">'. tl('press kit') .'</a></strong>
					<ul class="uk-nav uk-nav-side">';

if (count(TranslateTool::getLanguages()) > 1) {
	echo '<li class="language-select"><a>'. tl('Language: ') .'<select onchange="document.location = \''. htmlspecialchars($game) .'&l=\'+ this.value;">';
	foreach (TranslateTool::getLanguages() as $tag => $name)
	{
		echo '<option value="'. $tag .'" '. ($tag == $language ? 'selected':'') .'>'. htmlspecialchars($name) .'</option>';
	}
	echo '</select></a></li>';
	echo '<li class="uk-nav-divider"></li>';
}
		
echo '					<li><a href="#factsheet">'. tl('Factsheet') .'</a></li>
						<li><a href="#description">'. tl('Description') .'</a></li>
						<li><a href="#history">'. tl('History') .'</a></li>
						<li><a href="#projects">'. tl('Projects') .'</a></li>
						<li><a href="#trailers">'. tl('Videos') .'</a></li>
						<li><a href="#images">'. tl('Images') .'</a></li>
						<li><a href="#logo">'. tl('Logo & Icon') .'</a></li>';
if( count($awards) > 0 ) echo('<li><a href="#awards">'. tl('Awards & Recognition') .'</a></li>');
if( count($quotes) > 0 ) echo('<li><a href="#quotes">'. tl('Selected Articles') .'</a></li>');
if( $press_request == TRUE) { echo '<li><a href="#preview">'. tl('Request Press Copy') .'</a></li>'; }
if( $monetize >= 1) { echo '<li><a href="#monetize">'. tl('Monetization Permission') .'</a></li>'; }
echo '						<li><a href="#links">'. tl('Additional Links') .'</a></li>
						<li><a href="#about">'. tl('About %s', COMPANY_TITLE) .'</a></li>
						<li><a href="#credits">'. tl('Team') .'</a></li>
						<li><a href="#contact">'. tl('Contact') .'</a></li>
					</ul>
				</div>
				<div id="content" class="uk-width-medium-3-4">';

if( file_exists($games_location.$game."/images/header.png") ) {
	echo '<img src="'.$games_location.$game.'/images/header.png" class="header">';
}

echo '					<div class="uk-grid">
						<div class="uk-width-medium-2-6">
							<h2 id="factsheet">'. tl('Factsheet'). '</h2>
							<p>
								<strong>'. tl('Developer:'). '</strong><br/>
								<a href="../'. $languageQuery .'">'. COMPANY_TITLE .'</a><br/>
								'. tl('Based in %s', COMPANY_BASED) .'
							</p>
							<p>
								<strong>'. tl('Release date:'). '</strong><br/>
								'. GAME_DATE .'
							</p>

							<p>
								<strong>'. tl('Platforms:'). '</strong><br />';

for( $i = 0; $i < count($platforms); $i++ )
{
	$name = $link = "";
	foreach( $platforms[$i]['platform']->children() as $child )
	{
		if( $child->getName() == "name" ) {
			$name = $child;
		} else if( $child->getName() == "link" ) {
			$link = $child;
		}
	}
	echo '<a href="'.$link.'">'.$name.'</a><br/>';
}

echo '							</p>
							<p>
								<strong>'. tl('Website:'). '</strong><br/>
								<a href="'. GAME_WEBSITE .'">'. parseLink(GAME_WEBSITE) .'</a>
							</p>
							<p>
								<strong>'. tl('Regular Price:'). '</strong><br/>';

if( count($prices) == 0 )
{
	echo '-';
}
else
{
	echo '<table>';
	for( $i = 0; $i < count($prices); $i++ )
	{
		$currency = $value = "";

		foreach( $prices[$i]['price']->children() as $child )
		{
			if( $child->getName() == "currency" ) {
				$currency = $child;
			} else if( $child->getName() == "value" ) {
				$value = $child;
			}
		}
		echo '<tr><td>'.$currency.'</td><td>'.$value.'</td></tr>';
	}
	echo'</table>';
}

echo'							</p>
						</div>
						<div class="uk-width-medium-4-6">
							<h2 id="description">'. tl('Description'). '</h2>
							<p>'. GAME_DESCRIPTION .'</p>
							<h2 id="history">'. tl('History'). '</h2>';

for( $i = 0; $i < count($histories); $i++ )
{
	$header = $text ="";

	foreach( $histories[$i]['history']->children() as $child )
	{
		if( $child->getName() == "header" ) $header = $child;
		else if( $child->getName() == "text" ) $text = $child;
	}
	echo '<strong>'.$header.'</strong>
<p>'.$text.'</p>';
}

if( defined("GAME_HISTORY") ) {
	echo '<p>'. GAME_HISTORY .'</p>';
}

for( $i = 0; $i < count($histories); $i++ ) {
	$header = $text ="";

	foreach( $histories[$i]['history']->children() as $child )
	{
		if( $child->getName() == "header" ) {
			$header = $child;
		} else if( $child->getName() == "text" ) {
			$text = $child;
		}
	}
	echo '<strong>'.$header.'</strong><p>'.$text.'</p>';
}

echo '							<h2>'. tl('Features'). '</h2>
							<ul>';

for( $i = 0; $i < count($features); $i++ )
{
	echo '<li>'.$features[$i].'</li>';
}

echo '							</ul>
						</div>
					</div>

					<hr>

					<h2 id="trailers">'. tl('Videos'). '</h2>';

if( count($trailers) == 0 )
{
	echo '<p>'. tlHtml('There are currently no trailers available for %s. Check back later for more or <a href="#contact">contact us</a> for specific requests!', GAME_TITLE) .'</p>';
}
else
{
	for( $i = 0; $i < count($trailers); $i++ )
	{
		$name = $youtube = $vimeo = $mov = $mp4 = "";
		$ytfirst = -1;

		foreach( $trailers[$i]['trailer']->children() as $child )
		{
			if( $child->getName() == "name" ) {
				$name = $child;
			} else if( $child->getName() == "youtube" ) { 
				$youtube = $child; 
			
				if( $ytfirst == -1 ) { 
					$ytfirst = 1; 
				} 
			} else if( $child->getName() == "vimeo" ) {
				$vimeo = $child; if( $ytfirst == -1 ) {
					$ytfirst = 0;
				}
			} else if( $child->getName() == "mov" ) {
				$mov = $child;
			} else if( $child->getName() == "mp4" ) {
				$mp4 = $child;
			}
		}
				
		if( strlen($youtube) + strlen($vimeo) > 0 )				
		{
			echo '<p><strong>'.$name.'</strong>&nbsp;';
			$result = "";

			if( strlen( $youtube ) > 0 ) {
				$result .= '<a href="https://www.youtube.com/watch?v='.$youtube.'">YouTube</a>, ';
			}
			if( strlen( $vimeo ) > 0 ) {
				$result .= '<a href="https://www.vimeo.com/'.$vimeo.'">Vimeo</a>, ';
			}
			if( strlen( $mov ) > 0 ) {
				$result .= '<a href="'.$games_location.$game.'/trailers/'.$mov.'">.mov</a>, ';
			}
			if( strlen( $mp4 ) > 0 ) {
				$result .= '<a href="'.$games_location.$game.'/trailers/'.$mp4.'">.mp4</a>, ';
			}

			echo substr($result, 0, -2);

			if( $ytfirst == 1 ) 
			{
				echo '<div class="uk-responsive-width iframe-container">
		<iframe src="https://www.youtube.com/embed/'. $youtube .'" frameborder="0" allowfullscreen></iframe>
</div>';
			} elseif ( $ytfirst == 0 ) {
				echo '<div class="uk-responsive-width iframe-container">
		<iframe src="https://player.vimeo.com/video/'.$vimeo.'" frameborder="0" allowfullscreen></iframe>
</div>';
			}
			echo '</p>';
		}				
	}
}

echo '					<hr>

					<h2 id="images">'. tl('Images') .'</h2>';

if( file_exists($games_location.$game."/images/images.zip") )
{
	$filesize = filesize($games_location.$game."/images/images.zip");
	if( $filesize > 1024 && $filesize < 1048576 ) {
		$filesize = (int)( $filesize / 1024 ).'KB';
	}
	if( $filesize > 1048576 ) {
		$filesize = (int)(( $filesize / 1024 ) / 1024 ).'MB';
	}

	echo '<a href="'. $games_location.$game .'/images/images.zip"><div class="uk-alert">'. tl('download all screenshots & photos as .zip (%s)', $filesize) .'</div></a>';
}

echo '<div class="uk-grid images">';
if ($handle = opendir($games_location.$game.'/images'))
{
	$found = 0;
	/* This is the correct way to loop over the directory. */
	while (false !== ($entry = readdir($handle)))
	{
		if( substr($entry,-4) == ".png" || substr($entry,-4) == ".gif" )
		{
			if( substr($entry,0,4) != "logo" && substr($entry,0,4) != "icon" && substr($entry,0,6) != "header" )
			{	
				echo '<div class="uk-width-medium-1-2"><a href="'. $games_location.$game .'/images/'. $entry .'"><img src="'. $games_location.$game .'/images/'.$entry.'" alt="'.$entry.'" /></a></div>';
				$found++;
			}
		}
	}
}
echo '</div>';

closedir($handle);

if ($found == 0) {
	echo '<p class="images-text">'. tlHtml('There are currently no screenshots available for %s. Check back later for more or <a href="#contact">contact us</a> for specific requests!', GAME_TITLE) .'</p>';
}
					
echo '					<hr>

					<h2 id="logo">'. tl('Logo & Icon') .'</h2>';

if( file_exists($games_location.$game."/images/logo.zip") )
{
	$filesize = filesize($games_location.$game."/images/logo.zip");
	if( $filesize > 1024 && $filesize < 1048576 ) {
		$filesize = (int)( $filesize / 1024 ).'KB';
	}
	if( $filesize > 1048576 ) {
		$filesize = (int)(( $filesize / 1024 ) / 1024 ).'MB';
	}

	echo '<a href="'.$games_location.$game.'/images/logo.zip"><div class="uk-alert">'. tl('download logo files as .zip (%s)', $filesize) .'</div></a>';
}

echo '<div class="uk-grid images">';

if( file_exists($games_location.$game.'/images/logo.png') ) {
	echo '<div class="uk-width-medium-1-2"><a href="'.$games_location.$game.'/images/logo.png"><img src="'.$games_location.$game.'/images/logo.png" alt="logo" /></a></div>';
}

if( file_exists($games_location.$game.'/images/icon.png') ) {
	echo '<div class="uk-width-medium-1-2"><a href="'.$games_location.$game.'/images/icon.png"><img src="'.$games_location.$game.'/images/icon.png" alt="logo" /></a></div>';
}

echo '</div>';

if( !file_exists($games_location.$game.'/images/logo.png') && !file_exists($games_location.$game.'/images/icon.png')) {
	echo '<p>'. tlHtml('There are currently no logos or icons available for %s. Check back later for more or <a href="#contact">contact us</a> for specific requests!', GAME_TITLE) .'</p>';
}

echo '<hr>';

if( count( $awards ) > 0 )
{
	echo('<h2 id="awards">'. tl('Awards & Recognition') .'</h2>');
	echo('<ul>');
	
	for( $i = 0; $i < count($awards); $i++ )
	{
		$description = $info = "";
		foreach( $awards[$i]['award']->children() as $child )
		{
			if( $child->getName() == "description" ) {
				$description = $child;
			} else if( $child->getName() == "info" ) {
				$info = $child;
			}
		}
		echo '<li>"'.$description.'" <cite>'.$info.'</cite></li>';
	}
	
	echo '</ul>';
	echo '<hr>';
}

if( count($quotes) > 0 )
{
	echo '					<hr>
			
						<h2>'. tl('Selected Articles') .'</h2>
						<ul>';

	for( $i = 0; $i < count($quotes); $i++ )
	{
		$name = $description = $website = $link = "";
		foreach( $quotes[$i]['quote']->children() as $child )
		{
			if( $child->getName() == "description" ) {
				$description = $child;
			} else if( $child->getName() == "name" ) {
				$name = $child;
			} else if( $child->getName() == "website" ) {
				$website = $child;
			} else if( $child->getName() == "link" ) {
				$link = $child;
			}
		}
		echo '<li>"'.$description.'" <br/>
<cite>- '.$name.', <a href="'.$link.'">'.$website.'</a></cite></li>';
	}
	
	echo '</ul>';
	echo '<hr>';
}


if( $press_request == TRUE )
{
	echo '<h2 id="preview">'.tl('Request Press Copy').'</h2>';
	echo '<p>'. tl("Please fill in your e-mail address below to complete a distribute() request and we'll get back to you as soon as a press copy is available for you.") .'<br/>';
	echo '<div id="mailform">';
	echo '<form id="pressrequest" class="uk-form" method="POST" action="'.$url.'">';
	echo '<input type="email" id="email" name="email" placeholder="name@yourdomain.com" style="width:100%;"></input>';
	echo '<input type="hidden" id="key" name="key" value="'.$key.'"></input><br/>';
	echo '<input type="submit" class="uk-button" id="submit-button" value="'. tl('request a press copy') .'" style="width:100%;"></input>';
	echo '<p>'. tlHtml('Alternatively, you can always request a press copy by <a href="#contact">sending us a quick email</a>.').'</p>';
	echo '</div>';
	echo '<hr>';
} else {
	if( $press_request_fail == TRUE ) {
		echo '<h2 id="preview">'.tl('Request Press Copy').'</h2>';
		echo '<p>'.$press_request_fail_msg.'</p>';
		echo '<hr>';
	}
	if( $press_request_outdated_warning == TRUE ) {
		echo '<h2 id="preview">'.tl('Request Press Copy').'</h2>';
		echo '<p>'.tl("We are afraid this developer has not upgraded their presskit() to use distribute(). For security purposes, this form has been disabled.").'</p>';
		echo '<hr>';
	}
}

if( $monetize >= 1 )
{
	echo '<h2 id="monetize">'. tl('Monetization Permission') .'</h2>';
	if( $monetize == 1 ) echo('<p>'. tl('%s does currently not allow for the contents of %s to be published through video broadcasting services.', COMPANY_TITLE, GAME_TITLE) .'</p>');
	if( $monetize == 2 ) echo('<p>'. tl('%s does allow the contents of this game to be published through video broadcasting services only with direct written permission from %s. Check at the bottom of this page for contact information.', COMPANY_TITLE, GAME_TITLE) .'</p>');
	if( $monetize == 3 ) echo('<p>'. tl('%s allows for the contents of %s to be published through video broadcasting services for non-commercial purposes only. Monetization of any video created containing assets from %s is not allowed.', COMPANY_TITLE, GAME_TITLE, GAME_TITLE) .'</p>');
	if( $monetize == 4 ) echo('<p>'. tl('%s allows for the contents of %s to be published through video broadcasting services for any commercial or non-commercial purposes. Monetization of videos created containing assets from %s is legally & explicitly allowed by %s.', COMPANY_TITLE, GAME_TITLE, GAME_TITLE, COMPANY_TITLE) .' '. tlHtml('This permission can be found in writing at <a href="%s">%s</a>.', 'https://'. $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], 'https://'. $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) .'</p>');
	echo '<hr>';
}


echo '					<h2 id="links">'. tl('Additional Links'). '</h2>';
		
for( $i = 0; $i < count($additionals); $i++ )
{
	$title = $description = $link = "";
	foreach( $additionals[$i]['additional']->children() as $child )
	{
		if( $child->getName() == "title" ) {
			$title = $child;
		} else if( $child->getName() == "description" ) {
			$description = $child;
		} else if( $child->getName() == "link" ) {
			$link = $child;
		}
	}

	if( strpos(parseLink($link),'/') !== false ) {
		$linkTitle = substr(parseLink($link),0,strpos(parseLink($link),'/'));
	} else { $linkTitle = $link; }
	
	echo '<p>
	<strong>'.$title.'</strong><br/>
	'.$description.' <a href="'.$link.'" alt="'.parseLink($link).'">'.$linkTitle.'</a>.
</p>';
}

echo '					<hr>

					<h2 id="about">'. tl('About %s', COMPANY_TITLE) .'</h2>
					<p>
						<strong>'. tl('Boilerplate'). '</strong><br/>
						'. COMPANY_DESCRIPTION .'
					</p>

					<p>
						<strong>'. tl('More information'). '</strong><br/>
						'. tlHtml('More information on %s, our logo & relevant media are available <a href="%s">here</a>.', COMPANY_TITLE, '../'. $languageQuery). '
					</p>
					
					<hr>

					<div class="uk-grid">
						<div class="uk-width-medium-1-2">
							<h2 id="credits">'. tl('%s Credits', GAME_TITLE) .'</h2>';

for( $i = 0; $i < count($credits); $i++ )
{
	$previous = $website = $person = $role = "";
	foreach( $credits[$i]['credit']->children() as $child )
	{
		if( $child->getName() == "person" ) {
			$person = $child;
		} else if( $child->getName() == "previous" ) {
			$previous = $child;
		} else if( $child->getName() == "website" ) {
			$website = $child;
		} else if( $child->getName() == "role" ) {
			$role = $child;
		}
	}

	echo '<p>';
				
	if( strlen($website) == 0 )
	{
		echo '<strong>'.$person.'</strong><br/>'.$role;
	}
	else
	{
		echo '<strong>'.$person.'</strong><br/><a href="'.$website.'">'.$role.'</a>';
	}

	echo '</p>';
}

echo '						</div>
						<div class="uk-width-medium-1-2">
							<h2 id="contact">'. tl('Contact') .'</h2>';

for( $i = 0; $i < count($contacts); $i++ )
{
	$link = $mail = $name = "";
	foreach( $contacts[$i]['contact']->children() as $child )
	{
		if( $child->getName() == "name" ) {
			$name = $child;
		} else if( $child->getName() == "link" ) {
			$link = $child;
		} else if( $child->getName() == "mail" ) {
			$mail = $child;
		}
	}

	echo '<p>';

	if( strlen($link) == 0 && strlen($mail) > 0 ) {
		echo '<strong>'.$name.'</strong><br/><a href="mailto:'.$mail.'">'.$mail.'</a>';
	}
	if( strlen($link) > 0 && strlen($mail) == 0 ) {
		echo '<strong>'.$name.'</strong><br/><a href="'.$link.'">'.parseLink($link).'</a>';
	}

	echo '</p>';
}

echo '						</div>
					</div>

					<hr>

					<p><a href="https://dopresskit.com/">presskit()</a> by Rami Ismail (<a href="https://www.vlambeer.com/">Vlambeer</a>) - also thanks to <a href="https://dopresskit.com/#thanks">these fine folks</a></p>
				</div>
			</div>
		</div>

		<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
		<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery.imagesloaded/3.0.4/jquery.imagesloaded.js"></script>		
		<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/masonry/3.1.2/masonry.pkgd.min.js"></script>
		<script type="text/javascript">
			$( document ).ready(function() {
				var container = $(\'.images\');

				container.imagesLoaded( function() {
					container.masonry({
						itemSelector: \'.uk-width-medium-1-2\',
					});
				});
			});
		</script>';
if ( defined("ANALYTICS") && strlen(ANALYTICS) > 10 )
{
	echo '<script type="text/javascript">
	var _gaq = _gaq || [];
	_gaq.push([\'_setAccount\', \'' . ANALYTICS . '\']);
	_gaq.push([\'_trackPageview\']);

	(function() {
		var ga = document.createElement(\'script\'); ga.type = \'text/javascript\'; ga.async = true;
		ga.src = (\'https:\' == document.location.protocol ? \'https://ssl\' : \'http://www\') + \'.google-analytics.com/ga.js\';
		var s = document.getElementsByTagName(\'script\')[0]; s.parentNode.insertBefore(ga, s);
	})();
</script>';
}
echo'	</body>
</html>';

?>
