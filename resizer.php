<?php

/*
 
    image resizer script by Luka Kladaric <luka@kladaric.net>
    
    
    use via .htaccess mod_rewrite rules:
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteRule ^(.*\.jpg).([0-9x]+)px.jpg$ bin/resizer.php?from=$1&res=$2 [L,NC]
    
    then calling
        http://yoursite/images/foo.jpg.700px.jpg
    will load the 700px wide version of the image
    
    the script will save the result back to images/foo.jpg.700px.jpg so the next time the URL is
    requested, it will go straight to the actual file, skipping the PHP script invocation
    
    supported size params:
        700px - 700px wide - scale resize
        x300px - 300px tall - scale resize
        700x300px - scale & crop optimally (hopefully) to produce EXACTLY 700x300px image
    
    REQUIRES Imagick's convert app
    
    CONFIGURATION
     - make sure $approot is correct
     - make sure you have /usr/bin/convert
     - make sure the RewriteRule has the correct path to the script
     - adjust $nice to suite your needs (the default 10 should ensure resizer doesn't hog the spotlight)

*/

// get rid of magic quotes, if set
if (get_magic_quotes_gpc()) {
    $_GET = undoMagicQuotes($_GET);
    $_POST = undoMagicQuotes($_POST);
    $_COOKIE = undoMagicQuotes($_COOKIE);
    $_REQUEST = undoMagicQuotes($_REQUEST);
}


chdir($approot = ".."); // force current working directory to app root

$nice = "nice -n 10 ";

$convert = $nice."/usr/bin/convert";

$source = ifsetor($_GET['from']);

if (!is_file($source))
    exit("No such file");

$res = trim(ifsetor($_GET['res'], ''));

$target = $source . ".{$res}px.jpg";

if (!$res)
    exit ("Bad dimensions");

$resizeto = 0;
if (preg_match("@^x[0-9]+$@", $res)) {
    $resizeto = $res;
    $hintwidth = $hintheight = 2*$res;
} elseif (preg_match("@([0-9]+)x([0-9]+)@", $res, $matches)) {
    $width = $matches[1];
    $height = $matches[2];

    $hintwidth = 2*$matches[1];
    $hintheight = 2*$matches[2];

    $newratio = $width/$height;
    $oldimg = imagecreatefromjpeg($source);
    $oldratio = imagesx($oldimg)/imagesy($oldimg);
    unset($oldimg);
	
    if ($newratio < $oldratio) {
        $resizeto = "x{$height}";
    }
} elseif (isintstr($res)) {
    $width = $res;
    $height = null;

    $hintwidth = $hintheight = 2*$res;
} else {
    die ("Bad dimensions");
}
$resizeto = ($resizeto)?$resizeto:$width;


$crop_cmd = ($height)?" -gravity Center -crop {$width}x{$height}+0+0 ":"";
@exec ($x = "{$convert} -define jpeg:size={$hintwidth}x{$hintheight} -thumbnail {$resizeto} {$crop_cmd} \"{$source}[0]\" \"{$target}\"");

// did the convert work?
if (is_file($target)) {
	// if it worked, do we know what the original request was? redirect if we do.
	if (isset($_SERVER["REQUEST_URI"]))
		httpredirect($_SERVER["REQUEST_URI"]);
	
	// otherwise, serve the file through readfile()
	@header("Content-Type: image/jpg"); 
	readfile ($target);
	die;
}

echo "Image resize failed";



/* support functions */

function ifsetor(&$variable, $default = null) {
   if (isset($variable)) {
       $tmp = $variable;
   } else {
       $tmp = $default;
   }
   return $tmp;
}

function httpredirect($url, $permanent = false) {
    // if $url is just a querystring, figure out the
    // current page and send the full location
    if (substr($url, 0, 1) == '?') {
        $baseurl = parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
        $url = $baseurl . $url;
    }

    if (!headers_sent()) {
        if ($permanent)
            header( "HTTP/1.1 301 Moved Permanently" );
        header("Location: {$url}",true);
    } else {
        echo "<a href=\"{$url}\">click here to continue</a>";
    }
    die();
}

function undoMagicQuotes($array, $topLevel=true) {
    $newArray = array();
    foreach($array as $key => $value) {
        if (!$topLevel) {
            $key = stripslashes($key);
        }
        if (is_array($value)) {
            $newArray[$key] = undoMagicQuotes($value, false);
        }
        else {
            $newArray[$key] = stripslashes($value);
        }
    }
    return $newArray;
}
