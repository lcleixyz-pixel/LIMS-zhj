<?php
 /**
  * This is core configuration file.
  *
  * Use it to configure core behavior of Cake.
  *
  * PHP 5
  *
  * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
  * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
  *
  * Licensed under The MIT License
  * For full copyright and license information, please see the LICENSE.txt
  * Redistributions of files must retain the above copyright notice.
  *
  * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
  * @link          http://cakephp.org CakePHP(tm) Project
  * @package       app.Config
  * @since         CakePHP(tm) v 0.2.9
  * @license       http://www.opensource.org/licenses/mit-license.php MIT License
  */

Configure::write("debug",0);
Configure::write("Error", array("handler" => "ErrorHandler::handleError","level" => E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE,"trace" => false));
Configure::write("Exception", array("handler" => "ErrorHandler::handleException","renderer" => "ExceptionRenderer","log" => true));
Configure::write("App.encoding", "UTF-8");
Configure::write("Routing.prefixes", array("admin"));
Configure::write("Cache.disable", true);
// Configure::write("Cache.check", true);
Configure::write("Session", array("defaults" => "php","Session.timeout" => 20,"Session.checkAgent"=>true));
Configure::write("Asset.timestamp", true);
Configure::write("Acl.classname", "DbAcl");
Configure::write("Acl.database", "default");
Cache::config("default", array("engine" => "File","duration" => 30,"probability" => 100,"path" => CACHE,"prefix" => "cake_","lock" => false,"serialize" => true,));
$engine = "File";
 // In development mode, caches should expire quickly.
$duration = "+999 days";
if (Configure::read("debug") > 0) {$duration = "+10 seconds";}
$prefix = "myapp_";
Cache::config("_cake_core_", array("engine" => $engine,"prefix" => $prefix . "cake_core_","path" => CACHE . "persistent" . DS,"serialize" => ($engine === "File"),"duration" => $duration));
Cache::config("_cake_model_", array("engine" => $engine,"prefix" => $prefix . "cake_model_","path" => CACHE . "models" . DS,"serialize" => ($engine === "File"),"duration" => $duration));
Configure::write("MinifyAsset", true);
Configure::write("MediaPath", WWW_ROOT);
Configure::write("ApiPath", "https://api.flinkiso.com/");
Configure::write('WkHtmlToPdfPath', '/usr/local/bin/wkhtmltopdf');
Configure::write('PDFTkPath', '/usr/bin/pdftk '); // always add space at the end for this

// *****************************************************************************
//                       UPDATE FOLLOWING VALUES
// *****************************************************************************
// * A random string used in security hashing methods.
Configure::write("Security.salt", "41charalphanumericrandomstring00000abcdef");
// * A random numeric string (digits only) used to encrypt/decrypt strings.
Configure::write("Security.cipherSeed", "12345678901234567890123456789");
// * A Select correct PHP timezone
date_default_timezone_set("Asia/Kolkata");
// * add ONLYOFFICE secret here 
Configure::write("onlyofficesecret","");
// * Add ONLYOFFICE IP ADDRESS
Configure::write("OnlyofficePath", "http:///web-apps/apps/api/documents/api.js");
Configure::write("OnlyofficeConversionApi", "http://");  // ONLYOFFICE IP ADDRESS
// * Change default data format
Configure::write('dateFormat', 'd/m/Y'); // options d-m-Y, dd-mm-YY, d M Y etc
Configure::write('dateTimeFormat', 'd/m/Y H:i:s'); // options d-m-Y, dd-mm-YY, d M Y etc
?>
