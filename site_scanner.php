<?
require_once('img_fixer.php');

if (PHP_SAPI === 'cli') {
 $home=getcwd();

 if (is_file($argv[1])) {  
  if (is_numeric($argv[2])) { new img_embedder($argv[1], $argv[2]); } 
  else { new img_embedder($argv[1]); }
 } elseif($argv[1] == '--help' || $argv[1] == '-h' || $argv[1] == '/?') {
    echo img_embedder::help(); exit();
 } elseif($argv[1] == '--scan' || $argv[1] == '-s') {
  foreach (glob('{*.html,*/*.html,*/*/*.html}', GLOB_BRACE) as $f) {
   chdir(dirname($f));
//    echo basename($f)." is in home: ".  getcwd()." \n";
   new img_embedder(basename($f));
   chdir($home);
  }
 } else{
    echo img_embedder::help(); exit();
 }    
} else { die("This tool is not designed for web use\n"); }