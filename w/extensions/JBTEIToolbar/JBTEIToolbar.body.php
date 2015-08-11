<?php

/**
 * Copyright (C) 2013 Richard Davis
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License Version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 *
 * @package MediaWiki
 * @subpackage Extensions
 * @author Richard Davis <r.davis@ulcc.ac.uk>
 * @author Ben Parish <b.parish@ulcc.ac.uk>
 * @copyright 2013 Richard Davis
 */

class JBTEIToolbarHooks {

	/**
	 * editPageShowEditFormInitial hook
	 *
	 * Adds the modules to the edit form
	 *
	 * @param $toolbar array list of toolbar items
	 * @return bool
	 */
	public function editPageShowEditFormInitial( $toolbar , $out) {

		global $wgOut;

		$pageTitle = $wgOut->getTitle();

		if( $this->isInEditMode( $pageTitle ) === false ){
			return TRUE;
		}
        
		$wgOut->addModules( 'ext.JBTEIToolbar' );

		return TRUE;
	}


	private function isInEditMode( $pageTitle ){
    
    global $wgNewManuscriptOptions,$wgWebsiteRoot; 

		/*
		 * TODO: Need to be able to override this in LocalSettings.php
		 */

    //checks for default Transcription Desk files. 
		if (   preg_match( '^\w+\/(\d\d\d)\/(\d\d\d)\/(\d\d\d)^', $pageTitle ) ){
			return TRUE;
		}
    
    //checks for manuscripts. 
    $images_root_dir = $wgNewManuscriptOptions['zoomimages_root_dir'];
    $manuscripts_namespace = $wgNewManuscriptOptions['manuscripts_namespace']; 
   
    $page_title_with_namespace = strip_tags($pageTitle);    
    $page_title = trim(str_replace($manuscripts_namespace,"",$page_title_with_namespace));    
    $page_title = strtolower($page_title);
    
    $page_title_array = explode("/", $page_title);
    
    $user_fromurl = isset($page_title_array[0]) ? $page_title_array[0] : null;
    $filename_fromurl = isset($page_title_array[1]) ? $page_title_array[1] : null;
    
    if(!isset($user_fromurl) || !isset($filename_fromurl) || count($filename_fromurl) > 50 || !ctype_alnum($user_fromurl) || !ctype_alnum($filename_fromurl)){
      return false; 
    }
    
    $document_root = $wgWebsiteRoot;  
    $zoom_images_file = $document_root . DIRECTORY_SEPARATOR . $images_root_dir . DIRECTORY_SEPARATOR . $user_fromurl . DIRECTORY_SEPARATOR . $filename_fromurl;
    
    if(!file_exists($zoom_images_file)){
      return false; 
    }
    
		return true;
	}
}