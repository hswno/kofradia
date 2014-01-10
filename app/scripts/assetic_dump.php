<?php

/**
 * This script dumps assetic files
 */

require dirname(__FILE__)."/../essentials.php";

use Assetic\AssetWriter;
use Assetic\AssetManager;
use Assetic\Factory\AssetFactory;
use Assetic\FilterManager;
use Assetic\Filter\UglifyCssFilter;


$factory = new AssetFactory(PATH_APP."/assets");
if (!MAIN_SERVER) $factory->setDebug(true);

$am = new AssetManager();
$factory->setAssetManager($am);

$fm = new FilterManager();
$fm->set('uglifycss', new UglifyCssFilter('/usr/local/bin/uglifycss'));
$factory->setFilterManager($fm);


$am->set('login', $factory->createAsset(
	array(PATH_APP."/assets/css/logginn.css"),
	array('?uglifycss'),
	array('name' => 'login', 'output' => 'css/*')
));


$am->set('default', $factory->createAsset(
	array(PATH_APP."/assets/css/default/*.css"),
	array('?uglifycss'),
	array('name' => 'default', 'output' => 'css/*')
));


$am->set('guest', $factory->createAsset(
	array(PATH_APP."/assets/css/guest.css"),
	array('?uglifycss'),
	array('name' => 'guest', 'output' => 'css/*')
));


$am->set('node', $factory->createAsset(
	array(PATH_APP."/assets/css/node.css"),
	array('?uglifycss'),
	array('name' => 'node', 'output' => 'css/*')
));


$am->set('doc', $factory->createAsset(
	array(PATH_APP."/assets/css/doc.css"),
	array('?uglifycss'),
	array('name' => 'doc', 'output' => 'css/*')
));


$writer = new AssetWriter(PATH_PUBLIC."/assets");
$writer->writeManagerAssets($am);