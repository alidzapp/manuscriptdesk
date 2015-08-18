<?php
/**
 * Some of the functions (getUploadForm, showUploadForm and all the function in the class UploadFileForm and UploadSourceFile) have been copied from
 * includes/specials/SpecialUpload.php and slightly altered in order to change the functionality for the purpose of this extension.
 * 
 * Possible problems: How to save the images in such a way that Gent can access and store these? 
 * Possible problems: What happens when 20 people create a new manuscript at the same time? Will the server be able to handle this? You can delay the processing 
 * (people will have to wait longer), or see if you can have a server with more RAM 
 * Possible problems: The new wikipage is being made with help of a requestcontext that has been made on this page. Maybe some data for the new page will not be right.
 * 
 * Todo: Perhaps add the options 'Sort by Date' and 'Sort by Title' in Special:UserPage
 * 
 * Todo: Try to create unit tests for the extensions. See: https://www.mediawiki.org/wiki/Manual:PHP_unit_testing/Writing_unit_tests_for_extensions , and check 
 * http://www.gossamer-threads.com/lists/wiki/mediawiki/520085 why the tests directory is missing for this version. Perhaps try to get them from github? 
 * 
 * Todo: Check the limitations for the collation tool, and alter them if needed  
 *   
 * Todo: Also install a fresh copy of mediawiki at home? 
 * 
 * Todo: Perhaps make a javascript file that shows how many charachters you have left on a page...
 * 
 * Todo: Write documentation on how to install the software, and the structure of the software. Also, increase the amount of comments
 * 
 * Todo: Some errors are probably redundant. Additional testing is needed to see if these are necessary. 
 */

/**
 * This file is part of the newManuscript extension
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

class SpecialnewManuscript extends SpecialPage {
  
  /**
   * This class handles file uploads for the newManuscript extension. 
   */
      
  public $request;
	public $uploadbase_object;
	public $upload_was_clicked;
	public $token_is_ok;
  public $posted_title;
  public $posted_collection; 
  public $title_ok;
  public $max_upload_size;
  public $maximum_pages_per_collection; 
  
  private $allowed_file_extensions;  
  private $target_dir; 
  private $user_name; 
  private $document_root; 
  private $max_manuscripts; 
  private $manuscripts_namespace; 
  private $new_page_title_object; 
  private $zoomimages_root_dir; 
  
  //class constructor
	public function __construct(){
    
    global $wgNewManuscriptOptions,$wgWebsiteRoot; 
   	 
    $this->max_upload_size = $wgNewManuscriptOptions['max_upload_size'];
    $this->allowed_file_extensions = $wgNewManuscriptOptions['allowed_file_extensions'];
    $this->max_manuscripts = $wgNewManuscriptOptions['max_manuscripts'];
    $this->maximum_pages_per_collection = $wgNewManuscriptOptions['maximum_pages_per_collection'];
    
    $this->document_root = $wgWebsiteRoot;
    $this->target_dir = $this->document_root . DIRECTORY_SEPARATOR .  $wgNewManuscriptOptions['original_images_dir']; 
    
    $this->manuscripts_namespace = $wgNewManuscriptOptions['manuscripts_namespace'];
    $this->zoomimages_root_dir = $wgNewManuscriptOptions['zoomimages_root_dir'];

    
    include('prepareSlicer.php');
		parent::__construct('newManuscript');
  }
  
  /**
   * Load requests
   */
  private function loadRequest($user_object){
    
    $this->request = $request = $this->getRequest();
    $this->uploadbase_object = UploadBase::createFromRequest($request);
    $this->upload_was_clicked = $request->wasPosted()
			&& ( $request->getCheck('wpUpload'));
				//|| $request->getCheck('wpUploadIgnoreWarning'));
    
    // If it was posted check for the token (no remote POST'ing with user credentials)
		$token = $request->getVal('wpEditToken');
		$this->token_is_ok = $this->getUser()->matchEditToken($token);   
    $this->posted_title = $request->getText('wptitle_field');
    $this->posted_collection = $request->getText('wpcollection_field');
    $this->user_name = $user_object->getName();
  }

	/**
	 * This function checks if the user has the right to create new manuscript pages, loads the requests and checks if the request was valid. 
   * If a valid request was posted, this request is processed. Otherwise, the default page is shown, if the user has not reached the maximum allowed manuscript uploads. 
   * Also, the page title is set.
	 */
	public function execute() {
        
    $out = $this->getOutput();
    $user_object = $out->getUser();
    
    if(!in_array('ManuscriptEditors',$user_object->getGroups())){
      return $out->addWikiMsg('newmanuscript-nopermission');
    }

    $this->loadRequest($user_object);
    
    if ($this->token_is_ok &&($this->uploadbase_object && $this->upload_was_clicked)){
      //the user clicked 'Create New Manuscript Page', and so go to processUpload()
      return $this->processUpload();
    }
 
    //set page title
    $out->setPageTitle($this->msg('newmanuscript-title'));
    
    $max_uploads_reached = $this->checkNumberOfUploads();
    
    //If the user has already uploaded the maximum amount of allowed manuscript pages, do not display the form
    if($max_uploads_reached){
      return $out->addWikiMsg('newmanuscript-maxreached'); 
    }
    
    $this->getUploadForm()->show();
	}
   
  /**
   * This function checks whether the user has reached the maximum of allowed uploads
   */
  private function checkNumberOfUploads(){
        
    $max_manuscripts = $this->max_manuscripts; 
    $zoomimages_root_dir = $this->zoomimages_root_dir;   
    $user_name = $this->user_name;  
    
    $zoomimages_dir = $this->document_root . DIRECTORY_SEPARATOR . $zoomimages_root_dir . DIRECTORY_SEPARATOR . $user_name; 
    
    if(!file_exists($zoomimages_dir)){
      return false; 
    }
    
    //Count the number of files in the directory. The count is subtracted by 2 because the function also counts '.' and '..' as separate directories. 
    $number_of_uploads = count(scandir($zoomimages_dir)) - 2;
    
    if($number_of_uploads <= $max_manuscripts){
      return false;
    }else{
      return true; 
    }
  }
  
  /** 
   * This function gets the uploadform
   * 
   * @param type $message
   * @return type
   */
  private function getUploadForm($message = ''){
   
    $context = new DerivativeContext($this->getContext());
    
    //get the collections of the current user
    $collections_current_user = $this->getCollectionsCurrentUser();
    
    if(!empty($collections_current_user)){
      $collections_string = implode(', ', $collections_current_user);
      
      $collections_message = $this->msg('newmanuscript-collections') . $collections_string;
      
    }else{
      $collections_message = "";
    }

    $form = new UploadFileForm($context, $collections_message);
        
    //Add upload error message. 
		$form->addPreText($message);
    
    //This is needed to redisplay the form in case there was an upload error
    $form->setSubmitCallback(array('SpecialnewManuscript', 'showUploadError2')); 
       
		return $form;
  }
  
  /**
   * This function retrieves the collections of the current user
   *  
   * @return type
   */
  private function getCollectionsCurrentUser(){
    
    $dbr = wfGetDB(DB_SLAVE);
    
    $user_name = $this->user_name; 
    $collections_current_user = array();
    
    $conds = array(
     'manuscripts_user = ' . $dbr->addQuotes($user_name), 
     );

     //Database query
    $res = $dbr->select(
     'manuscripts', //from
     array(
       'manuscripts_collection',//values
        ),
     $conds, //conditions
     __METHOD__,
     array(
       'ORDER BY' => 'manuscripts_collection',
     )
     );

    //while there are results
    while ($s = $res->fetchObject()){
      
      //add to the $collections_current_user array, when the collection does not equal "", "none", and when the collection is not already in the array
      if($s->manuscripts_collection !== "" && $s->manuscripts_collection !== "none" && !in_array($s->manuscripts_collection, $collections_current_user)){
        $collections_current_user[] = $s->manuscripts_collection;
      }
    }    
    
    return $collections_current_user; 
  }

  /**
   * This function processes upload requests if the form was posted
   * 
   */
  private function processUpload(){
    
    $posted_title = $this->posted_title;
    $uploadbase_object = $this->uploadbase_object;
    $collection = $this->posted_collection; 
    $collection_error = "";
        
    //check if the $posted_title is valid
    list($new_page_url, $local_url, $title_error) = $this->checkTitle($posted_title);
    
    //set the collection name to "none" if no collection name was given
    if($collection === ""){
      $collection = "none";
    }else{  
      $collection_error = $this->checkCollection($collection);
    }
    
    $target_dir = $this->target_dir; 
    $user_name = $this->user_name; 
    $target_dir = $target_dir . DIRECTORY_SEPARATOR . $user_name . DIRECTORY_SEPARATOR . $posted_title;
   
    $title_object = $uploadbase_object->getTitle();
    //get the $file_name from the $title_object
    $file_name = isset($title_object) ? $title_object->getText() : "";
    
    if(null !== pathinfo($file_name,PATHINFO_EXTENSION)){
      $extension = pathinfo($file_name,PATHINFO_EXTENSION);
    }else{
      $extension = null; 
    }
    
    $magic = MimeMagic::singleton();
    $temp_path = $uploadbase_object->getTempPath();
    
    //this function tries to guess the mime type by, for example, opening the file, and checking the headers. See 'includes/MimeMagic.php 
    $mime = strtolower($magic->guessMimeType($temp_path));
           
    $target_file = $target_dir . DIRECTORY_SEPARATOR . $posted_title . '.' . $extension;
        
    //check for various aspects that could return an error   
    if($title_error !== "" && $collection_error !== ""){
      return $this->showUploadError($this->msg($title_error) . "<br>" . $this->msg($collection_error));
    }
        
    if($title_error !== ""){
      return $this->showUploadError($this->msg($title_error));
    }
    
    if($collection_error !== ""){
      return $this->showUploadError($this->msg($collection_error));
    }
      
    if($temp_path === ""){
      return $this->showUploadError($this->msg('newmanuscript-error-nofile'));
    }
   
    if(getimagesize($_FILES["wpUploadFile"]["tmp_name"]) === false){
      return $this->showUploadError($this->msg('newmanuscript-error-noimage'));
    }
         
    if (file_exists($target_file)){
      //following error will only trigger if somehow an earlier attempt with this title did not complete (yet). In the case this error triggers, it means
      //that the initial upload exists, but there is no corresponding wiki page (yet), otherwise a $title_error should not be empty.
      // Additional testing needed to see if this error is necessary. 
      return $this->showUploadError($this->msg('newmanuscript-error-page'));         
    }
    
    if ($uploadbase_object->getFileSize() > $this->max_upload_size) {
      return $this->showUploadError($this->msg('newmanuscript-error-toolarge'));
    }
    
    if($extension === ""){
      return $this->showUploadError($this->msg('newmanuscript-error-noextension'));
    }
      
    if($extension !== $this->allowed_file_extensions[0] && $extension !== $this->allowed_file_extensions[1]){
      return $this->showUploadError($this->msg('newmanuscript-error-fileformat'));
    }
    
    if(!strpos($mime,$this->allowed_file_extensions[0]) && !strpos($mime,$this->allowed_file_extensions[1])){
      return $this->showUploadError($this->msg('newmanuscript-error-fileformat'));
    }
            
    if($uploadbase_object::detectScript($temp_path,$mime,$extension) === true){
      return $this->showUploadError($this->msg('newmanuscript-error-scripts'));
    }
       
    //make the target directory if it does not exist yet
    if (!file_exists($target_dir)) {
      mkdir($target_dir, 0755, true);
    }
    
    $upload_succesfull = move_uploaded_file($temp_path, $target_file); 

    if(!$upload_succesfull){
      return $this->showUploadError($this->msg('newmanuscript-error-upload'));
    }

    $prepare_slicer = new prepareSlicer($posted_title,$target_file);
    
    //execute the slicer
    $status = $prepare_slicer->execute();

    if($status !== true){
      unlink($target_file);
      
      if($status === 'slicer-error-execute'){
        //something went wrong when executing the slicer, so delete all export files, if they exist
        $prepare_slicer->deleteExportFiles(); 
      }
      
      //get the error message
      $slicer_error_message = $this->msg($status);

      return $this->showUploadError($slicer_error_message);
    }
    
    //create a new wikipage
    $wikipage_status = $this->createNewWikiPage();
    
    if($wikipage_status !== true){
       //something went wrong when creating a new wikipage, so delete all export files, if they exist
      $prepare_slicer->deleteExportFiles(); 
      
      return $this->showUploadError($this->msg($wikipage_status));
    }
    
    //store information about the new uploaded manuscript page in the 'manuscripts' table
    $status = $this->writeToDB($posted_title, $collection, $user_name,$new_page_url);

    if(!$status){
      //delete all exported files if writing to the database failed, and show an error
      $prepare_slicer->deleteExportFiles(); 

      return $this->showUploadError($this->msg('newmanuscript-error-database'));
    }
    
    //if no errors, and slice succesfull, redirect to the new page
    return $this->getOutput()->redirect($local_url);  
	}
    
 /**
  * This function checks if posted title is empty, contains invalid charachters, is too long, or already exists in the database.
  * 
  * @global type $wgUser
  * @param type $posted_title
  * @return type
  */
  private function checkTitle($posted_title){
    
    $user_name = $this->user_name; 
    $manuscripts_namespace = $this->manuscripts_namespace; 
    $posted_title = strip_tags($posted_title);
    $title_error = "";
    $new_page_url = "";
    $local_url = null; 

    if($posted_title === ""){
      $title_error = 'newmanuscript-error-notitle';
       
    }elseif(!ctype_alnum($posted_title)){
      $title_error = 'newmanuscript-error-charachters';
      
    }elseif(strlen($posted_title) > 50){
      $title_error = 'newmanuscript-error-toolong';
        
    }else{
      $user_url = $user_name;
      $new_page_url = trim($manuscripts_namespace . $user_url . '/' . $posted_title);
      
      if(null !== Title::newFromText($new_page_url)){
        $title_object = Title::newFromText($new_page_url);
        $local_url = $title_object->getLocalURL();
        
        if($title_object->exists()){
         $title_error = 'newmanuscript-error-exists';
        }
      }      
    }
            
    $this->new_page_title_object = isset($title_object) ? $title_object : null; 

    return array($new_page_url, $local_url, $title_error); 
  }
  
  /**
   * This function checks if $posted_collection contains invalid charachters, is too long, or if the collection has reached the maximum allowed manuscript pages
   * 
   * @param type $posted_collection
   * @return string
   */
  private function checkCollection($posted_collection){
    
    if(!ctype_alnum($posted_collection)){
      $collection_error = 'newmanuscript-error-collectioncharachters';
        
    }elseif(strlen($posted_collection) > 50){
      $collection_error = 'newmanuscript-error-collectiontoolong';
      
    }else{
      $collection_error = $this->checkNumberOfPagesPostedCollection($posted_collection);
    }
    
    return $collection_error; 
    
  }
  
  /**
   * This functions checks if the collection already reached the maximum allowed manuscript pages
   * 
   * @param type $posted_collection
   * @return string
   */
  private function checkNumberOfPagesPostedCollection($posted_collection){
    
    $dbr = wfGetDB(DB_SLAVE);
 
    $conds = array(
      'manuscripts_user = ' . $dbr->addQuotes($this->user_name),
      'manuscripts_collection = ' . $dbr->addQuotes($posted_collection),
      );
        
      //Database query
    $res = $dbr->select(
      'manuscripts', //from
      array(
        'manuscripts_url',//values
         ),
      $conds, //conditions
      __METHOD__,
      array(
        'ORDER BY' => 'manuscripts_lowercase_title',
      )
      );
        
    if ($res->numRows() > $this->maximum_pages_per_collection){
      return 'newmanuscript-error-collectionmaxreached';
    }
   
    return ""; 
  }
  
  /**
   * This function makes a new wikipage, and auto loads wiki text needed for the metatable.
   */
  private function createNewWikiPage(){
    
    global $wgMetaTableTag; 
    
    $title_object = $this->new_page_title_object;  
    $context = $this->getContext();  
    $article = Article::newFromTitle($title_object, $context);
      
    $open_tag = $wgMetaTableTag['open'];
    $close_tag = $wgMetaTableTag['close'];
        
    $wiki_text = "
    This page has not been transcribed yet. 



<!-- only edit metatable values below this line -->
    $open_tag
    title=
    author=
    date= 
    original_image_name= 
    image_number= 
    page_number= 
    info_in_main_headings_field= 
    marginal_summary_numbering=
    category=
    number_of_pages=
    recto_verso=
    penner=
    watermarks=
    marginals=
    paper_producer=
    corrections=
    produced_in_year=
    notes_public=
    id_number=
    $close_tag";
    
    $editor_object = new EditPage($article); 
    $content_new = new wikitextcontent($wiki_text);
    //see includes/EditPage.php of an example on how this function is used
    $doEditStatus = $editor_object->mArticle->doEditContent($content_new, $editor_object->summary, 97,
                        false, null, $editor_object->contentFormat);
    
    //when the script has reached this function, the function should never return an error because all the checks have been done beforehand. 
    //However, if unexpectedly the page could not be created, $errors can be inspected to see what the problem was. 
    if (!$doEditStatus->isOK() ) {
			$errors = $doEditStatus->getErrorsArray();
      return 'newmanuscript-error-wikipage';
    }
    
    return true;
  }
  
  /**
   * This function insert data into the manuscripts table
   * 
   * @param type $posted_title
   * @param type $user_name    
   * @param type $new_page_url
   * @return boolean
   */
  	private function writeToDB($posted_title, $collection, $user_name,$new_page_url){
      
    $date = date("d-m-Y H:i:s");  
    $date2 = date('YmdHis');

    $lowercase_title = strtolower($posted_title);
    $lowercase_collection = strtolower($collection);
  
		$dbw = wfGetDB(DB_MASTER);
		$dbw->insert('manuscripts', //select table
      array( //insert values
      'manuscripts_id'                   => null,
			'manuscripts_title'                => $posted_title,
			'manuscripts_user'                 => $user_name,
			'manuscripts_url'                  => $new_page_url, 
      'manuscripts_date'                 => $date,
      'manuscripts_lowercase_title'      => $lowercase_title,
      'manuscripts_collection'           => $collection, 
      'manuscripts_lowercase_collection' => $lowercase_collection,  
      'manuscripts_datesort'             => $date2,   
			),__METHOD__,
			'IGNORE' );
		if ($dbw->affectedRows()){
      //insert succeeded
			return true;
		}else{
		  //return error
	    return false;
		}
	}
         
  /**
	 * Show the upload form with error message, but do not stash the file.
	 *
	 * @param string $message HTML string
	 */
	private function showUploadError($message){
		$message = '<h2>' . $this->msg( 'uploadwarning' )->escaped() . "</h2>\n" .
			'<div class="error">' . $message . "</div>\n";
    $this->getUploadForm($message)->show();
  }
  
 /**
  * Callback function. Makes sure the page is redisplayed in case there was an error. 
  * 
  * @param type $form_data
  * @return boolean
  */ 
  static function showUploadError2($form_data){
      return false; 
    }
}
 
class UploadFileForm extends HTMLForm {
  
/**
 * This sub class displays the form on the page. Parts of this class have been copied from includes/specials/specialUpload.php,
 * and altered for the purpose of this extension
 */
    
	public $max_upload_size; 
  
  private $collections_message; 
  private $allowed_file_extensions; 
 
  //class constructor
	public function __construct(IContextSource $context = null, $collections_message) {
    
    global $wgNewManuscriptOptions;
        
    $this->max_upload_size = $wgNewManuscriptOptions['max_upload_size'];
    $this->collections_message = $collections_message; 
    $this->allowed_file_extensions =  $wgNewManuscriptOptions['allowed_file_extensions'];

		$descriptor = $this->getSourceSection();

    //reference to the parent constructor
		parent::__construct($descriptor, $context, 'upload'); 

		# Set some form properties
		$this->setSubmitText($this->msg('newmanuscript-submit'));
		$this->setSubmitName('wpUpload');
		# Used message keys: 'accesskey-upload', 'tooltip-upload'
		$this->setSubmitTooltip('upload');
		$this->setId('mw-upload-form');
	}
  
  /**
   * This function adds the title information, the title field, the uploadfile button and the extension information
   * 
   * For title-field see upload-title in newManuscript.i18n.php
   * 
   * @return string
   */
  private function getSourceSection(){
    
		$descriptor = array();
         
    $descriptor['title_message'] = array(
      'type' => 'info',
      'section' => 'title',
      'default' => '<br>' . $this->msg('newmanuscript-title-instruction') . '<br>',
      'raw' => true,    
    );          
    
    $descriptor['title_field'] = array(
      'section' => 'title',
      'label' => 'New Title:', 
      'class' => 'HTMLTextField', 
      'id' => 'enter_title',
      'maxlength'=> 50,
    );
    
    if($this->collections_message === ""){
      $collections_message = "";
    }else{
      $collections_message = $this->collections_message . '.<br>';
    }
    
    $descriptor['collection_message'] = array(
      'type' => 'info',
      'section' => 'title',
      'default' => '<br>' . $this->msg('newmanuscript-collections-instruction') . '<br>' . $collections_message,
      'raw' => true,    
    );    
    
    $descriptor['collection_field'] = array(
      'section' => 'title',
      'label' => 'Collection:', 
      'class' => 'HTMLTextField', 
      'maxlength'=> 50,
    );
		
		$descriptor['UploadFile'] = array(
		  'class' => 'UploadSourceFile', //UploadSourceFile
			'section' => 'source',
			'type' => 'file',
			'id' => 'wpUploadFile',
			'label-message' => 'sourcefilename',
			'help' => $this->msg( 'upload-maxfilesize',
				$this->getContext()->getLanguage()->formatSize($this->max_upload_size)
			)->parse() .
				$this->msg('word-separator' )->escaped() .
				$this->msg('upload_source_file' )->escaped(),
			'checked' => true,
		);

		$descriptor['Extensions'] = array(
			'type' => 'info',
			'section' => 'source', 
			'default' => $this->getExtensionsMessage(),
			'raw' => true,
		);
              
    //add html form entries
		return $descriptor;
	}
  
  /**
   * Retrieves a list of allowed file types. 
   * 
   * @return string
   */
  	private function getExtensionsMessage() {
		
    $wg_modified_file_extensions = $this->allowed_file_extensions; 

    $extensionsList =
      '<div id="mw-upload-permitted">' .
      $this->msg(
        'upload-permitted',
        $this->getContext()->getLanguage()->commaList( array_unique($wg_modified_file_extensions) )
      )->parseAsBlock() .
      "</div>\n";

		return $extensionsList;
	}
  
	/**
	 * Add the upload JS and show the form.
	 */
	public function show() {
		$this->addUploadJS();
		parent::show();
	}
  
	/**
	 * Add upload JS to the OutputPage (the JS construct the preview image)
   * 
   * Location of the javascript files:
   * resources/src/mediawiki.special/mediawiki.special.upload.js
   * skins/common/upload.js
   * 
   * Additional information about the modules can be found in resources/Resources.php
	 */
	private function addUploadJS() {
 
		$scriptVars = array(
			'wgMaxUploadSize' => $this->max_upload_size, 
		);

		$out = $this->getOutput();
		$out->addJsConfigVars($scriptVars);

		$out->addModules( array(
			'mediawiki.legacy.upload', // Old form stuff...
			'mediawiki.special.upload', // Newer extras for thumbnail preview.
    ));
  }
}

/**
 * A form field that contains a radio box in the label
 */
class UploadSourceFile extends HTMLTextField {  
}

























