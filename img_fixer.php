<?php
/**
 * @author Karl Holz
 * @package img_embedder
 * @version 0.2
 * 
 * 
 * 
 * Copyright (c) 2012 Karl Holz, www.salamcast.com
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this
 * software and associated documentation files (the "Software"), 
 * to deal in the Software without restriction, including without limitation the rights to use, 
 * copy, modify, merge, publish, distribute, sublicense, 
 * and/or sell copies of the Software, and to permit persons to whom the Software is 
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all copies or 
 * substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS 
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF 
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.   
 * IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
 * CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, 
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE 
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 * 
 * This Class will search your HTML/xHTML document for image files and embed them into 
 * the document Please note this will make your html file a lot bigger, so use with care. 
 */


/**
 * img embeder
 */
class img_embedder {
 /**
  * regex match  
  */
 protected $match='/[A-Za-z0-9%.,_-]+\/[A-Za-z0-9%.,_-]+?\.(jpg|png|gif)/';
 /**
  * array to hold img uri's 
  */
 protected $img=array();
 /**
  * array of base64 encoded strings 
  */
 protected $base=array();   
 /**
  * array of sizes 
  */
 protected $size=array();
 /**
  * max single file 
  */
 static protected $max_size=51200; // 50kb
 protected $max_file=51200; // 50kb
 /**
  * max number of files 
  */
 //protected $max_img='10';
 
 /**
 * XSLT Style Sheet to suck the image url from the html document, 
 */
 function get_img() {
  return <<<IMG
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:h="http://www.w3.org/1999/xhtml"
  xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" 
  xmlns:iweb="http://www.apple.com/iweb"
  xmlns:iphoto="urn:iphoto:property"
  version="1.0">
 <xsl:output method="text"/>
  <xsl:template match="/">[img_files]<xsl:apply-templates select="//h:img|//img|//image|//itunes:image|//enclosure|//iweb:mip-thumbnail|//iweb:micro|//iphoto:thumbnail" />
 <xsl:apply-templates select="//@style" />
 </xsl:template>
 <xsl:template match="//h:img|//img|//image|//itunes:image|//enclosure" >
src[]=<xsl:value-of select="@src|url|@href|@url"/>
 </xsl:template>
  <xsl:template match="//iweb:mip-thumbnail|//iweb:micro|//iphoto:thumbnail" >
src[]=<xsl:value-of select="."/>
</xsl:template>
  <xsl:template match="//@style" >
style[]="<xsl:value-of select="."/>" </xsl:template>  
</xsl:stylesheet>
IMG
;
 }
 
 function __construct($imgfile, $max_size='') {
  if (is_file($imgfile)) {
//   chdir(dirname($imgfile));
//   $file=basename($imgfile);
   if (is_numeric($max_size)) { $this->max_file=$max_size; }
   $ext=pathinfo($imgfile, PATHINFO_EXTENSION);
   switch($ext) {
    case 'html': case 'xml': case 'rss':
     $img_list=$this->xsl_out($this->get_img(), $imgfile);
     if ($img_list) {
      $ini=parse_ini_string($img_list);
      if ($ini) { $k=0;
       // encode found img files in markup if it exists
       foreach ($ini['src'] as $i) {
        if ($this->add_img($k, urldecode($i))) { $k++; }
       }
       // parse style attribute
       if (array_key_exists('style', $ini)) {
        $f=preg_grep($this->match, $ini['style']);
        if($f) { // encode matched files if it exists
         foreach ($f as $n) {
          preg_match_all($this->match, $n, $ff);
          if ($this->add_img($k, $ff[0][0])) { $k++; }
         }
        }
       }
       // read html file into var
       $html=@file_get_contents($imgfile);
       // backup to a new file
       $this->write_file($imgfile.'.old', $html);
       // embed found image files in XHTML document 
       echo " * Starting to check and encode your selected images \n";
       $fixed=$this->fix_images($html);
       // over write input document
       $this->write_file($imgfile, $fixed);
       echo "*************************************************************\n";
       echo "Finished Embedding your linked images into your html file \n";
       echo "*************************************************************\n\n";
      } else {echo $img_list."\n"; echo("Failed to parse img list \n\n"); }
     } else { echo($imgfile." could contain invalid XML tags, please review file\n\n"); }
    break;
    case 'css':
        
    break;
    case 'js':
        
    break;
   }
  } else {  echo $imgfile." is NOT a valid file, please Try again\n\n"; }  
 }
 
 
 function add_img($k, $i) {
  if (is_file($i)){
   $file=$i;
  } else {
   $file=preg_replace('/http\:\/\/.{3}\.[a-z0-9]*\..{3}\//', '', $i);
   if (!is_file($file)) {
    echo " * The file ".$i." is  not Real or the reference is broken \n";  return FALSE;
   }

  }      
  if (filesize($file) < $this->max_file) {
   $this->img[$k]=$i;
   $this->base[$k]=$this->base64_encode_image($file);
   echo " * The Image ".$file." has been added to the embed list, file size: ".$this->get_kb($i)." kb \n";
   return TRUE;
  } else {
   echo " * The file: ".$file." is bigger than the maximum file size: ".$this->get_kb($this->max_file)." kb \n";
   return FALSE;
  }
 }
 
 /**
  * write file to disk
  * @param string $file
  * @param string $data
  * @return boolean 
  */
 function write_file($file, $data) {
  $fp = fopen($file, 'w');
  fwrite($fp, $data);
  fclose($fp);
  return TRUE;
 }
 
 function get_kb($i) {
  if (is_file($i)) {
   return round((filesize($i)/1024));
  } elseif (is_numeric($i)) {
   return round(($i/1024));
  } else { return 0; }
 }


 /**
  * fix images in $input
  * @param string $input
  * @return string
  */
 function fix_images($input) { return str_replace($this->img, $this->base, $input); }
 /**
  * xsl_out()
  *
  * @param string $xsltmpl XSLT stylesheet to be applied to XML
  * @param string $xml_load XML data
  * @param mixed $params to be passed to XSLT style sheet
  * @param string $file
  * @return string $xslproc->transformToXml($xml) transformed XML data
  */
 function xsl_out($xsltmpl, $xml_load, $param=array(), $file='') {
  $xml=new DOMDocument();
  if (!is_file($xml_load) ){ $xml->loadXML($xml_load); } 
  else { 
   if (! $xml->load(realpath($xml_load))) {
      echo " *** XML/XHTML failed to load ***\n"; 
      return FALSE;    
   }
  }
  //loads XSL template file
  $xsl=new DOMDocument();
  if (!is_file($xsltmpl)) { $xsl->loadXML($xsltmpl); } else {
   if(! $xsl->load(realpath($xsltmpl))) { 
       echo " *** XSLT style sheet failed to load ***\n"; 
       return FALSE; 
   }
  }
  //process XML and XSLT files and return result
  $xslproc = new XSLTProcessor();
  if (count($param) > 0) { $xslproc->setParameter('', $param); }
  $xslproc->importStylesheet($xsl);
  if ($file != '') { 
      if ($xslproc->transformToURI($xml, 'file://'.$file)) { return TRUE; } 
      else { return FALSE; } 
  } else { return $xslproc->transformToXml($xml); }
 }
 
/**
 * encode image file for faster page loading, less web requests because 
 * image files are embeded in the HTML file
 * @param type $imagefile
 * @return type 
 */
 function base64_encode_image ($imagefile) {
  $imgtype = array('jpg', 'gif', 'png');
  if (!file_exists($imagefile)) { echo("Image file name does not exist \n"); return $imagefile; }
  $filetype = strtolower(pathinfo($imagefile, PATHINFO_EXTENSION));
  if (in_array($filetype, $imgtype)){ 
      $imgbinary = fread(fopen($imagefile, "r"), filesize($imagefile));    
  } else { 
    echo ("Invalid image type, jpg, gif, and png is only allowed \n\n");  
    return $imagefile; 
  }
  return 'data:Image/' . $filetype . ';base64,' . base64_encode($imgbinary);
 }
 
 static function help() {
  system('clear');
  $size=self::$max_size;
  $s=$_SERVER['SCRIPT_NAME'];
  return <<<HELP
*************************************************************
    img embedder
    
This tool will scan your Input document for png/jpg/gif files
The Maximum file size is $size bytes


     $s [file] [size bytes]
     
     $s [option]

Options:

 --help, -h, /?  Display help menu
 --scan, -s      Scan all html files recursively in the 

*************************************************************

HELP
  ;
 }
}
?>