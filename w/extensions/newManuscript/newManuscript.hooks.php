<?php
/**  
 * This file is part of the newManuscript extension
 * Copyright (C) 2015 Arent van Korlaar
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
 * @author Arent van Korlaar <akvankorlaar'at' gmail 'dot' com> 
 * @copyright 2015 Arent van Korlaar
 * 
 * This file incorporates work covered by the following copyright and
 * permission notice: 
 * 
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

class newManuscriptHooks {
  
/**
 * This is the newManuscriptHooks class for the NewManuscript extension. Various aspects relating to interacting with 
 * the manuscript page (and other special pages in the extension)are arranged here, 
 * such as loading the zoomviewer, loading the metatable, adding CSS modules, loading the link to the original image, 
 * making sure a manuscript page can be deleted only by the user that has uploaded it (unless the user is a sysop), and preventing users from making
 * normal wiki pages on NS_MANUSCRIPTS (the manuscripts namespace identified by 'manuscripts:' in the URL)
 */
  
  public $viewer_mode = false;
  public $edit_mode = false;
    
  private $title_options_site_name;
  private $images_root_dir;
  private $mediawiki_dir;
  private $page_title;
  private $lang;
  private $viewer_type;
  private $user_fromurl;
  private $filename_fromurl;
  private $document_root; 
  private $manuscript_url_count_size;
  private $original_images_dir;
  private $allowed_file_extensions; 
  private $manuscripts_namespace_url;
  private $zoomimage_check_before_delete;
  private $original_image_check_before_delete;
  private $max_charachters_manuscript; 
   
 /**
	 * Assigns globals to properties
	 * Creates default values when these have not been set
	 */
  private function assignGlobalsToProperties($old_title = null){

    global $wgLang,$wgScriptPath,$wgOut,$wgNewManuscriptOptions,$wgWebsiteRoot;
    
    $this->manuscript_url_count_size = $wgNewManuscriptOptions['url_count_size'];
    $this->images_root_dir = $wgNewManuscriptOptions['zoomimages_root_dir'];
    $this->original_images_dir = $wgNewManuscriptOptions['original_images_dir'];
    $this->page_title = isset($old_title) ? $old_title : strip_tags($wgOut->getTitle());  
    $this->document_root = $wgWebsiteRoot; 
    
    $this->title_options_site_name = 'Manuscript Desk';   
    $this->mediawiki_dir  = $wgScriptPath;
    $this->lang = $wgLang->getCode();
    
    $this->allowed_file_extensions = $wgNewManuscriptOptions['allowed_file_extensions'];
    $this->manuscripts_namespace_url = $wgNewManuscriptOptions['manuscripts_namespace'];
    $this->max_charachters_manuscript = $wgNewManuscriptOptions['max_charachters_manuscript'];
    
    $this->zoomimage_check_before_delete = false;
    $this->original_image_check_before_delete = false; 
    
    return;
  }
      
  /**
	 * editPageShowEditFormFields hook
   * 
   * This function loads the zoomviewer if the editor is in edit mode. 
   * 	 
	 * @param $editPage EditPage
	 * @param $output OutputPage
	 * @return bool
	 */
  public function onEditPageShowEditFormInitial(EditPage $editPage, OutputPage &$output){

    if( isset( $_GET[ 'action' ] ) and $_GET[ 'action' ] !== 'edit' ){
      return true; 
    }
			
    $this->assignGlobalsToProperties();
   
		if(!$this->urlValid()){
			return true; 
		}
    
    $this->edit_mode = true; 
    
    $this->loadViewer($output);

    return true;
  }
  
  /**
   * This function loads the zoomviewer if the page on which it lands is a manuscript,
   * and if the url is valid.     
   * 
   * @global type $wgOut
   * @param type $output
   * @param type $article
   * @param type $title
   * @param type $user
   * @param type $request
   * @param type $wiki
   * @return boolean
   */
  public function onMediaWikiPerformAction($output,$article,$title,$user,$request,$wiki){
     
    if($wiki->getAction($request) !== 'view' ){
      return true; 
    }            
    
    $this->assignGlobalsToProperties();    

		if(!$this->urlValid()){
			return true; 
		}
  
    $collection = $this->getCollection();
    
    if($collection !== null){
      $output->addHTML('<h2>' . $collection . '</h2><br>');
    }
        
    $original_image_link = $this->getOriginalImageLink();
    
    $output->addHTML($original_image_link);
    
    $this->viewer_mode = true;
        
    $this->loadViewer($output);

    return true;
	}
  
  /**
   * This function retrieves the collection of the current page
   * 
   * @return type
   */
  private function getCollection(){
    
    $page_title = $this->page_title; 
    
    $dbr = wfGetDB(DB_SLAVE);
    
    $conds = array(
       'manuscripts_url = ' . $dbr->addQuotes($page_title),
     ); 
    
     //Database query
    $res = $dbr->select(
      'manuscripts', //from
      array(
        'manuscripts_collection',
      ),
      $conds, //conditions
      __METHOD__,
      array(
      'ORDER BY' => 'manuscripts_collection',
      )
    );
        
    //there should only be 1 result
    if ($res->numRows() === 1){
      
      $collection_name = $res->fetchObject()->manuscripts_collection; 
      
      if(!empty($collection_name) && $collection_name !== 'none'){
        return $collection_name; 
      }
    }                
           
    return null; 
  }
  
  /**
   * This function returns the link to the original image
   */
  private function getOriginalImageLink(){
    
    $partial_original_image_path = $this->constructOriginalImagePath();
    
    $original_image_path = $this->document_root . $partial_original_image_path; 
    
    if(!is_dir($original_image_path)){
      return "<b>Original image is not available</b>";
    }
    
    $file_scan = scandir($original_image_path);    
    $image_file = isset($file_scan[2])? $file_scan[2] : "";
    
    if($image_file === ""){
      return "<b>Original image is not available</b>";
    }
     
    $full_original_image_path = $original_image_path . $image_file; 
    
    if(!$this->isImage($full_original_image_path)){
      return "<b>Original image is not available</b>";
    }
    
    $link_original_image_path = $partial_original_image_path . $image_file; 
    
    return "<a href='$link_original_image_path'>Original Image</a>";   
  }
  
  /**
   * Construct the full path of the original image
   */
  private function constructOriginalImagePath(){
    
    $original_images_dir = $this->original_images_dir;
    $user_fromurl = $this->user_fromurl;
    $filename_fromurl = $this->filename_fromurl;
    
    $original_image_file_path = '/' . $original_images_dir . '/' . $user_fromurl . '/' . $filename_fromurl . '/';
    
    return $original_image_file_path;
  }
  
  /**
   * This function checks if the file is an image. This has been done earlier and more thouroughly when uploading, but these checks are just to make sure
   */
  private function isImage($file){
    
    if(null !== pathinfo($file,PATHINFO_EXTENSION)){
      $extension = pathinfo($file,PATHINFO_EXTENSION);
    }else{
      $extension = null; 
    }
          
    if($extension !== $this->allowed_file_extensions[0] && $extension !== $this->allowed_file_extensions[1]){
      return false;
    }
    
    if(getimagesize($file) === false){
      return false;
    }
    
    return true; 
  }
  
  /**
   * This function checks if the current page is a manuscript page
   * 
   * @return boolean
   */
  private function urlValid(){
    
    $page_title_with_namespace = $this->page_title;
    $manuscripts_namespace = $this->manuscripts_namespace_url; 
    $images_root_dir = $this->images_root_dir; 
    $document_root = $this->document_root;    
    
    if(substr($page_title_with_namespace,0,strlen($manuscripts_namespace)) !== $manuscripts_namespace){
      return false; 
    }
    
    $page_title = trim(str_replace($manuscripts_namespace,"",$page_title_with_namespace));  
    $page_title_array = explode("/", $page_title);
    
    $user_fromurl = isset($page_title_array[0]) ? $page_title_array[0] : null; 
    $filename_fromurl = isset($page_title_array[1]) ? $page_title_array[1] : null;
    
    if(!isset($user_fromurl) || !isset($filename_fromurl) || count($filename_fromurl) > 50 || !ctype_alnum($user_fromurl) || !ctype_alnum($filename_fromurl)){
      return false; 
    }
    
    $zoom_images_file = $document_root . DIRECTORY_SEPARATOR . $images_root_dir . DIRECTORY_SEPARATOR . $user_fromurl . DIRECTORY_SEPARATOR . $filename_fromurl;
    
    if(!file_exists($zoom_images_file)){
      return false; 
    }
    
    $this->user_fromurl = $user_fromurl;
    $this->filename_fromurl = $filename_fromurl; 
    
    return true;    
  }
      
  /**
	 * Adds the iframe HTML to the page. This HTML will be used by the zoomviewer so that it can load the correct image
	 *
	 * @param $output OutputPage
	 * @return bool
	 */
	private function loadViewer(OutputPage $output ){
    
    //The editor does not display correctly when 'ext.JBZV' is called using AddModuleStyles in edit mode. The reason for this is unknown.
    if($this->viewer_mode){
      $output->addModuleStyles('ext.JBZV');
    }
    
    if($this->edit_mode){
      $output->addModules('ext.JBZV');
    }
        
		$view_content = $this->formatIframeHTML();
		$output->addHTML($view_content);
    
    return true; 
	}
 
  /**
	 * Generates the HTML for the iframe
	 *
	 * @return string
	 */
  private function formatIframeHTML(){

		$mediawiki_dir  = $this->mediawiki_dir;
    
		$viewer_type = $this->getViewerType();

		if($viewer_type === 'js'){
      $viewer_path = 'tools/ajax-tiledviewer/ajax-tiledviewer.php';
		}else{
      $viewer_path = 'tools/zoomify/zoomifyviewer.php';
    }

		$image_file_path = $this->constructImageFilePath();
		$lang = $this->lang;
		$siteName	= $this->title_options_site_name;

		$iframeHTML = '<iframe id="zoomviewerframe" src="' .  $mediawiki_dir . '/extensions/newManuscript/' . $viewer_path . '?image=' . $image_file_path . '&amp;lang=' . $lang . '&amp;sitename=' . urlencode($siteName) . '"></iframe>';

		return $iframeHTML;
	}
  
 /**
	 * Gets the default viewer type.
	 *
	 * @return string
	 */ 
  private function getViewerType(){

		if($this->viewer_type !== NULL){
			return $this->viewer_type;
		}

		if($this->browserIsIE()){
			return 'js';
		}

		return 'zv';
	}
  
  /**
	 * Determines whether the browser is Internet Explorer.
	 *
	 * @return bool
	 */
	private function browserIsIE(){

		$user_agent = $_SERVER['HTTP_USER_AGENT'];

		if(preg_match('/MSIE/i', $user_agent)){
			return true;
		}

		return false;
	}
  
  /**
	 * Constructs the full path of the image to be passed to the iframe. 
	 *
	 * @return string
	 */
	private function constructImageFilePath(){
    
		$images_root_dir = $this->images_root_dir;
    $user_fromurl = $this->user_fromurl;
    $filename_fromurl = $this->filename_fromurl; 
    
    //DIRECTORY_SEPARATOR does not work here
    $image_file_path = '/' . $images_root_dir . '/' . $user_fromurl . '/' . $filename_fromurl . '/';

		return $image_file_path;
	}
  
  /**
   * The function register, registers the wikitext <metadata> </metadata>
   * with the parser, so that the metatable can be loaded. When these tags are encountered in the wikitext, the function render 
   * is called
   */
  public static function register(Parser &$parser){
		// Register the hook with the parser
		$parser->setHook('metatable', array('newManuscriptHooks', 'render'));

		return true;
	}
  
  /**
   * This function makes a new meta table object, extracts
   * the options in the tags, and renders the table 
   */
	public static function render($input, $args, Parser $parser){
		
		$meta_table = new metaTable(); 
    
    $meta_table->extractOptions($parser->replaceVariables($input));

		return $meta_table->renderTable($input);
	}
  
  /**
   * This function runs every time mediawiki gets a delete request. This function prevents
   * users from deleting manuscripts they have not uploaded
   * 
   * @param WikiPage $article
   * @param User $user
   * @param type $reason
   * @param type $error
   */
  public function onArticleDelete( WikiPage &$article, User &$user, &$reason, &$error ){
    
    $this->assignGlobalsToProperties();
    
    $page_title_with_namespace = $this->page_title;
    $manuscripts_namespace = $this->manuscripts_namespace_url; 
    
    if(substr($page_title_with_namespace,0,strlen($manuscripts_namespace)) !== $manuscripts_namespace){
      //this is not a manuscript. Allow deletion
      return true; 
    }
    
    $page_title = trim(str_replace($manuscripts_namespace,"",$page_title_with_namespace));  
    
    $page_title_array = explode("/", $page_title);
    $user_fromurl = isset($page_title_array[0]) ? $page_title_array[0] : null; 
    $user_name = $user->getName();  
    $user_groups = $user->getGroups();
        
    if(($user_fromurl === null || $user_name !== $user_fromurl) && !in_array('sysop',$user_groups)){     
        //deny deletion because the current user did not create this manuscript, and the user is not an administrator
        $error = '<br>You are not allowed to delete this page';
        return false; 
    }
    
    $document_root = $this->document_root; 
    $images_root_dir = $this->images_root_dir;
    
    $filename_fromurl = isset($page_title_array[1]) ? $page_title_array[1] : null; 
        
    $zoom_images_file = $document_root . DIRECTORY_SEPARATOR . $images_root_dir . DIRECTORY_SEPARATOR . $user_fromurl . DIRECTORY_SEPARATOR . $filename_fromurl;
    
    $url_count_size = $this->manuscript_url_count_size;
    
    //do not delete any additional files on server if the zoom images file does not exist,
    //if the url does not have the format of a manuscripts page, or if $filename_fromurl is null
    if(!file_exists($zoom_images_file) || count($page_title_array)!== $url_count_size || !isset($filename_fromurl)){
      
      return true; 
    }
    
    $this->user_fromurl = $user_fromurl; 
    $this->filename_fromurl = $filename_fromurl; 
        
    $this->deleteExportFiles($zoom_images_file);
    
    $this->deleteOriginalImage();
    
    $this->deleteDatabaseEntry($page_title);
    
    return true;    
  }
  
  /**
   * Check if all the default files are present, and delete all files
   */
  private function deleteExportFiles($zoom_images_file){
               
    $tile_group_url = $zoom_images_file . DIRECTORY_SEPARATOR . 'TileGroup0';
    $image_properties_url = $zoom_images_file . DIRECTORY_SEPARATOR . 'ImageProperties.xml';    
    
    if(!is_dir($tile_group_url) ||!is_file($image_properties_url)){
      return false; 
    }
    
    $this->zoomimage_check_before_delete = true; 
    
    return $this->deleteAllFiles($zoom_images_file);
  }
  
  /**
   * This function checks if the original image path file is valid, and then calls deleteAllFiles()
   * 
   * @return boolean
   */
  private function deleteOriginalImage(){
    
    $partial_original_image_path = $this->constructOriginalImagePath();
    $original_image_path = $this->document_root . $partial_original_image_path; 
    
    if(!is_dir($original_image_path)){
      return false; 
    }
    
    $file_scan = scandir($original_image_path);    
    $image_file = isset($file_scan[2])? $file_scan[2] : "";
    
    if($image_file === ""){
      return false;
    }
     
    $full_original_image_path = $original_image_path . $image_file; 
    
    if(!$this->isImage($full_original_image_path)){
      return false;
    }
    
    if (count($file_scan) > 3){
      return false; 
    }
    
    $this->original_image_check_before_delete = true; 
    
    return $this->deleteAllFiles($original_image_path);   
  }
     
  /**
   * This function deletes all files in $zoom_images_file. First a last check is done.
   * After this the function deletes files in $path
   *  
   * @param type $path
   * @return boolean
   */
  private function deleteAllFiles($path){
    
    if($this->zoomimage_check_before_delete || $this->original_image_check_before_delete){
    
      //start deleting files         
      if (is_dir($path) === true){
        $files = array_diff(scandir($path), array('.', '..'));

        foreach ($files as $file){
          //recursive call
          $this->deleteAllFiles(realpath($path) . DIRECTORY_SEPARATOR . $file);
        }

        return rmdir($path);

      }else if (is_file($path) === true){
        return unlink($path);
      }  
    }
    
    return false;   
  }  
  
  /**
   * This function deletes the entry for $page_title in the 'manuscripts' table
   */
  private function deleteDatabaseEntry($page_title){
    
    $manuscripts_namespace_url = $this->manuscripts_namespace_url;     
    $full_page_url = $manuscripts_namespace_url . $page_title; 
    
    $dbw = wfGetDB(DB_MASTER);
    
    $dbw->delete( 
      'manuscripts', //from
      array( 
      'manuscripts_url' => $full_page_url), //conditions
      __METHOD__ );
    
    	if ($dbw->affectedRows()){
        //something was deleted from the manuscripts table  
        return true;
		  }else{
        //nothing was deleted
        return false;
		}
	}
  
  /**
   * This function prevents users from saving new wiki pages on NS_MANUSCRIPTS when there is no corresponding file in the database
   * 
   * @param type $wikiPage
   * @param type $user
   * @param type $content
   * @param type $summary
   * @param type $isMinor
   * @param type $isWatch
   * @param type $section
   * @param type $flags
   * @param type $status
   */
  public function onPageContentSave( &$wikiPage, &$user, &$content, &$summary,
    $isMinor, $isWatch, $section, &$flags, &$status){
    
    $this->assignGlobalsToProperties();
    
    $page_title_with_namespace = $this->page_title;
    $manuscripts_namespace = $this->manuscripts_namespace_url; 
                 
    if(substr($page_title_with_namespace,0,strlen($manuscripts_namespace)) !== $manuscripts_namespace){
      //this is not a manuscript. Allow saving
      return true; 
    }
             
    $document_root = $this->document_root; 
    $images_root_dir = $this->images_root_dir;
    
    $page_title = trim(str_replace($manuscripts_namespace,"",$page_title_with_namespace));  
    
    $page_title_array = explode("/", $page_title);
    
    $user_fromurl = isset($page_title_array[0]) ? $page_title_array[0] : null; 
    $filename_fromurl = isset($page_title_array[1]) ? $page_title_array[1] : null; 
    
    $zoom_images_file = $document_root . DIRECTORY_SEPARATOR . $images_root_dir . DIRECTORY_SEPARATOR . $user_fromurl . DIRECTORY_SEPARATOR . $filename_fromurl;
      
    if(!file_exists($zoom_images_file) || !isset($user_fromurl) || !isset($filename_fromurl)){
      //the page is in NS_MANUSCRIPTS but there is no corresponding file in the database, so don't allow saving
      $status->fatal(new RawMessage("New manuscripts can only be created on the [[Special:newManuscript]] page"));   
      return true; 
    }
    
    //check if this page does not have more charachters than $max_charachters_manuscript
    $new_content = $content->mText; 
    
    if(strlen($new_content) > $this->max_charachters_manuscript){
       $status->fatal(new RawMessage("Your manuscript page already has more than the maximum allowed charachters. "));   
       return true; 
    }
    
    //this is a manuscript page, there is a corresponding file in the database, and $max_charachters_manuscript has not been reached, so allow saving
    return true;
    }
    
  /**
   * This function adds additional modules containing CSS before the page is displayed
   * 
   * @param OutputPage $out
   * @param Skin $ski
   */
  public function onBeforePageDisplay(OutputPage &$out, Skin &$ski ){

    $title_object = $out->getTitle();
    $page_title = $title_object->mPrefixedText; 

    if($title_object->getNamespace() === NS_MANUSCRIPTS){
      //add css for metatable
      $out->addModuleStyles('ext.metatable');
    }elseif($page_title === 'Special:AllManuscriptPages' || $page_title === 'Special:UserPage' || $page_title === 'Special:AllCollections'){
      //add css for correct button display on 'All Manuscripts' and 'User Page'
      $out->addModuleStyles("ext.buttonStyles");    
    }
    
    return true; 
  }
}