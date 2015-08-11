<?php
/**
 * This file is part of the collate extension
 * Copyright (C) 2015 Arent van Korlaar
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 
 * @package MediaWiki
 * @subpackage Extensions
 * @author Arent van Korlaar <akvankorlaar 'at' gmail 'dot' com> 
 * @copyright 2015 Arent van Korlaar
 */

/**
 * Usage: Add the following line in LocalSettings.php:
 * require_once( "$IP/extensions/collate/collate.php" );
 */

// Check environment
if (!defined( 'MEDIAWIKI')){
	echo( "This is an extension to the MediaWiki package and cannot be run standalone.\n" );
	die( -1 );
}

/* Configuration */

// Credits
$wgExtensionCredits['parserhook'][] = array(
	'path'           => __FILE__,
	'name'           => 'collate',
	'author'         => 'Arent van Korlaar',
	'version'        => '0.0.1',
	'url'            => '',
	'description'    => 'Allow collation of text',
	'descriptionmsg' =>  '',
);

// Shortcut to this extension directory
$dir = __DIR__ . '/';

// Auto load classes 
$wgAutoloadClasses['collateHooks']    = $dir . '/collate.hooks.php';
$wgAutoloadClasses['collate'] = $dir . '/collate.classes.php';
$wgExtensionMessagesFiles['collate']  = __DIR__ . '/collate.i18n.php';

// Register auto load for the special page classes and register special pages
$wgAutoloadClasses['SpecialallCollations'] = $dir . '/specials/SpecialallCollations.php';
$wgAutoloadClasses['SpecialBeginCollate'] = $dir . '/specials/SpecialBeginCollate.php';

$wgSpecialPages['allCollations'] = 'SpecialallCollations';
$wgSpecialPages['BeginCollate'] = 'SpecialBeginCollate';

//Extra file loaded later 
$wgResourceModules['ext.collate' ] = array(
		'localBasePath' => dirname( __FILE__ ) . '/css',  
		'styles'  => '/ext.collate.css',
);

$wgResourceModules['ext.buttonStylesCollation'] = array(
  	'localBasePath' => dirname( __FILE__ ) . '/css',  
		'styles'  => '/ext.buttonStylesCollation.css',
);

////Instantiate the collateHooks class and register the hooks
$collateHooks = new collateHooks();

$wgHooks['MediaWikiPerformAction'][] = array($collateHooks, 'onMediaWikiPerformAction');
$wgHooks['ArticleDelete'][] = array($collateHooks, 'onArticleDelete');
$wgHooks['PageContentSave'][] = array($collateHooks,'onPageContentSave');
$wgHooks['BeforePageDisplay'][] = array($collateHooks, 'onBeforePageDisplay');


