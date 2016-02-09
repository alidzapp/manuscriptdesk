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
class SpecialStylometricAnalysis extends ManuscriptDeskBaseSpecials {

    public $error_message = '';
    private $minimum_pages_per_collection;
    private $minimum_collections;
    private $user_name;
    private $python_path;
    private $form;
    private $base_outputpath;
    private $base_linkpath;
    private $full_outputpath1;
    private $full_outputpath2;
    private $min_words_collection;  //min words that should be in a collection. This is checked using str_word_count, but it has to be checked if str_word_count equals the number of tokens.
    private $collection_data;
    private $collection_name_data; 
    private $pystyl_config;

    //PyStyl $config_array information: 
    //removenonalpha : wheter or not to keep alphabetical symbols
    //lowercase : wheter or not to lowercase all charachters
    //tokenizer : str, default=None select the `nltk` tokenizer to be used. Currentlysupports: 'whitespace' (split on whitespace) and 'words' (alphabetic series of characters)
    //minimumsize : minimum size of texts (in tokens), to be included in the set of tokenized texts
    //maximumsize : maximum size of texts (in tokens). Longer texts will be truncated to max_size after tokenization
    //segmentsize : segment_size : int, default=0 The size of the segments to be extracted (in tokens). If `segment_size`=0, no segmentation will be applied to the tokenized texts
    //stepsize : The nb of words in between two consecutive segments (in tokens). If `step_size`=zero, non-overlapping segments will be created. Else, segments will partially overlap
    //removepronouns : Whether to remove personal pronouns. If the `corpus.language` is supported, we will load the relevant list from under `pystyl/pronouns`. The pronoun lists are identical to those for 'Stylometry with R'
    //vectorspace : Which vector space to use. Must be one of: 'tf', 'tf_scaled', 'tf_std', 'tf_idf', 'bin'
    //featuretype
    //ngramsize : The length of the ngrams to be extracted
    //mfi : The nb of most frequent items (words or ngrams) to extract
    //minimumdf : Proportion of documents in which a feature should minimally occur. Useful to ignore low-frequency features
    //maximumdf : Proportion of documents in which a feature should maximally occur. Useful for 'culling' and ignoring features which don't appear in enough texts
    //visualization1: The chosen visualization. This could be for example a dendrogram
    //visualization2: THe second chosen visualization

    public function __construct() {
        parent::__construct('StylometricAnalysis');
    }

    private function setVariables() {
        global $wgStylometricAnalysisOptions, $wgWebsiteRoot;

        $this->minimum_pages_per_collection = $wgStylometricAnalysisOptions['minimum_pages_per_collection'];
        $this->python_path = $wgStylometricAnalysisOptions['python_path'];
        $this->min_words_collection = $wgStylometricAnalysisOptions['min_words_collection'];
        $web_root = $wgWebsiteRoot;

        $user_object = $this->getUser();
        $this->user_name = $user_object->getName();

        $initial_analysis_dir = $wgStylometricAnalysisOptions['initial_analysis_dir'];
        $this->base_outputpath = $web_root . '/' . $initial_analysis_dir . '/' . $this->user_name;
        $this->base_linkpath = $initial_analysis_dir . '/' . $this->user_name;

        $this->minimum_collections = $wgStylometricAnalysisOptions['wgmin_stylometricanalysis_collections'];

        return true;
    }

    /**
     * Main entry point for the page
     */
    public function execute() {

        $this->setVariables();

        try {
            $this->checkPermission();

            if ($this->requestWasPosted()) {
                $this->processRequest();
                return true;
            }

            $this->getForm1();
            return true;
        } catch (Exception $e) {
            $this->handleExceptions($e);
            return false;
        }
    }

    /**
     * Process all requests
     */
    private function processRequest() {

        $this->checkEditToken();

        if ($this->form1WasPosted()) {
            $this->processForm1();
            return true;
        }

        if ($this->form2WasPosted()) {
            $this->processForm2();
            return true;
        }

        if ($this->savePageWasRequested()) {
            $this->processSavePageRequest();
            return true;
        }

        if ($this->redirectBackWasRequested()) {
            $this->getForm1();
            return true;
        }

        throw new \Exception('stylometricanalysis-error-request');
    }

    private function redirectBackWasRequested() {
        $request = $this->getRequest();
        if ($request->getText('redirect') !== '') {
            return true;
        }

        return false;
    }

    /**
     * Check if form 1 was posted
     */
    private function form1WasPosted() {
        $request = $this->getRequest();
        if ($request->getText('form1Posted') !== '') {
            return true;
        }

        return false;
    }

    /**
     * Check if form 2 was posted
     */
    private function form2WasPosted() {
        $request = $this->getRequest();
        if ($request->getText('form2Posted') !== '') {
            return true;
        }

        return false;
    }

    private function getForm1($error_message = '') {
        $user_collection_data = $this->getUserCollectionData();
        $viewer = new StylometricAnalysisViewer($this->getOutput());
        return $viewer->showForm1($user_collection_data, $error_message);
    }

    private function processForm1() {
        $form_data_getter = new FormDataGetter($this->getRequest(), new ManuscriptDeskBaseValidator());
        $this->form = 'Form1';
        $this->collection_data = $collection_data = $form_data_getter->getForm1Data();
        $this->collection_name_data = $this->constructCollectionNameData();
        $viewer = new StylometricAnalysisViewer($this->getOutput());
        return $viewer->showForm2($collection_data, $this->collection_name_data, $this->getContext());
    }

    private function processForm2() {
        $form_data_getter = new FormDataGetter($this->getRequest(), new ManuscriptDeskBaseValidator());
        $this->form = 'Form2';
        $this->collection_data = $form_data_getter->getForm2CollectionData();
        $this->collection_name_data = $this->constructCollectionNameData();
        $this->pystyl_config = $form_data_getter->getForm2PystylConfigurationData();

        $texts = $this->getPageTextsFromWikiPages();

        list($output_file_name1, $output_file_name2) = $this->constructPystylOutputFileNames();
        $this->constructFullOutputPathOfPystylOutputImages($output_file_name1, $output_file_name2);
        list($full_linkpath1, $full_linkpath2) = $this->constructFullLinkPathOfPystylOutputImages($output_file_name1, $output_file_name2);

        $this->setAdditionalPystylConfigValues($texts);
        $full_textfilepath = $this->constructFullTextfilePath();
        $this->insertPystylConfigIntoTextfile($full_textfilepath);
        $command = $this->constructShellCommandToCallPystyl();
        $pystyl_output = $this->callPystyl($command, $full_textfilepath);
        $this->deleteTextfile($full_textfilepath);
        $this->checkPystylOutput($pystyl_output);

        $new_page_url = $this->createNewPageUrl();
        $time = idate('U'); //time format integer (Unix Timestamp). This timestamp is used to see how old values are
        $date = date("d-m-Y H:i:s");
        $this->updateDatabase($time, $new_page_url, $date, $full_linkpath1, $full_linkpath2);

        $viewer = new StylometricAnalysisViewer($this->getOutput());
        return $viewer->showResult($this->pystyl_config, $this->collection_name_data, $full_linkpath1, $full_linkpath2, $time);
    }

    private function processSavePageRequest() {
        $form_data_getter = new FormDataGetter($this->getRequest(), new ManuscriptDeskBaseValidator());
        $time = $form_data_getter->getSavePageData();
        $new_page_url = $this->transferDatabaseDataAndGetNewPageUrl($time);
        $local_url = $this->createNewWikiPage($new_page_url);
        return $this->getOutput()->redirect($local_url);
    }

    private function createNewPageUrl() {
        $user_name = $this->user_name;
        $imploded_collection_name_data = implode('', $this->collection_name_data);
        $year_month_day = date('Ymd');
        $hours_minutes_seconds = date('his');
        return 'Stylometricanalysis:' . $user_name . "/" . $imploded_collection_name_data . "/" . $year_month_day . "/" . $hours_minutes_seconds;
    }

    private function constructCollectionNameData() {
        $this->collection_name_data = array();
        foreach ($this->collection_data as $index => $single_collection_data) {
            $this->collection_name_data[] = $single_collection_data['collection_name'];
        }
        return $this->collection_name_data;
    }

    /**
     * This function loops through all the posted collections, and
     * retrieves the text from the corresponding pages 
     */
    private function getPageTextsFromWikiPages() {

        //in $texts combined collection texts will be stored 
        $texts = array();
        $a = 1;

        //for collections, collect all single pages of a collection and merge them together
        foreach ($this->collection_data as $index => $single_collection_data) {

            $all_texts_for_one_collection = "";

            //go through all urls of a collection
            foreach ($single_collection_data as $index => $file_url) {

                if ($index !== 'collection_name') {

                    $title_object = Title::newFromText($file_url);

                    if (!$title_object->exists()) {
                        wfErrorLog($this->msg('stylometricanalysis-error-notexists') . "\r\n", $web_root . DIRECTORY_SEPARATOR . 'ManuscriptDeskDebugLog.log');
                        $this->form = 'Form1';
                        throw new \Exception('stylometricanalysis-error-notexists');
                    }

                    $single_page_text = $this->getSinglePageText($title_object);
                    //add $single_page_text to $single_page_texts
                    $all_texts_for_one_collection .= $single_page_text;
                }
            }

            $collection_n_words = str_word_count($all_texts_for_one_collection);
            $this->checkForCollectionErrors($collection_n_words);

            $collection_name = isset($single_collection_data['collection_name']) ? $single_collection_data['collection_name'] : 'collection' . $a;

            //add the combined texts of one collection to $texts
            $texts["collection" . $a] = array(
              "title" => "$collection_name",
              "target_name" => "$collection_name",
              "text" => "$all_texts_for_one_collection",
            );

            $a += 1;
        }

        return $texts;
    }

    /**
     * This function checks for collection errors based on the number of words in the collection
     */
    private function checkForCollectionErrors($collection_n_words) {

        $pystyl_config = $this->pystyl_config;

        if ($collection_n_words < $this->min_words_collection) {
            $this->form = 'Form1';
            throw new \Exception('stylometricanalysis-error-toosmall');
        }

        if ($collection_n_words < $pystyl_config['minimumsize']) {
            throw new \Exception('stylometricanalysis-error-minsize');
        }

        if ($collection_n_words < ($pystyl_config['segmentsize'] + $pystyl_config['stepsize'])) {
            throw new \Exception('stylometricanalysis-error-segmentsize');
        }

        if ($collection_n_words < $pystyl_config['ngramsize']) {
            throw new \Exception('stylometricanalysis-error-ngramsize');
        }

        return true;
    }

    private function constructPystylOutputFileNames() {

        $imploded_collection_name_data = implode('', $this->collection_name_data);
        $year_month_day = date('Ymd');
        $hours_minutes_seconds = date('his');

        $file_name1 = $imploded_collection_name_data . $year_month_day . $hours_minutes_seconds . '.jpg';
        $file_name2 = $imploded_collection_name_data . $year_month_day . $hours_minutes_seconds . 2 . '.jpg';

        return array($file_name1, $file_name2);
    }

    private function constructFullOutputPathOfPystylOutputImages($file_name1, $file_name2) {
        $this->full_outputpath1 = $this->base_outputpath . '/' . $file_name1;
        $this->full_outputpath2 = $this->base_outputpath . '/' . $file_name2;

        if (is_file($this->full_outputpath1) || is_file($this->full_outputpath2)) {
            throw new \Exception('stylometricanalysis-error-internal');
        }

        return true;
    }

    private function constructFullLinkPathOfPystylOutputImages($file_name1, $file_name2) {
        $full_linkpath1 = '/' . $this->base_linkpath . '/' . $file_name1;
        $full_linkpath2 = '/' . $this->base_linkpath . '/' . $file_name2;
        return array($full_linkpath1, $full_linkpath2);
    }

    private function constructShellCommandToCallPystyl() {
        $python_path = $this->python_path;
        $dir = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'PyStyl' . DIRECTORY_SEPARATOR . 'pystyl' . DIRECTORY_SEPARATOR . 'ManuscriptDeskAnalysis.py';
        //test.py      
        return $python_path . ' ' . $dir;
    }

    private function savePageWasRequested() {
        $request = $this->getRequest();
        if ($request->getText('save_current_page') === '') {
            return false;
        }

        return true;
    }

    /**
     * This function constructs the config array that will be sent to Pystyl
     */
    private function setAdditionalPystylConfigValues($texts) {
        $this->pystyl_config['texts'] = $texts;
        $this->pystyl_config['full_outputpath1'] = $this->full_outputpath1;
        $this->pystyl_config['full_outputpath2'] = $this->full_outputpath2;
        $this->pystyl_config['base_outputpath'] = $this->base_outputpath;
        return true;
    }

    /**
     * This function constructs a temporary textfile in which the data for the analysis will be placed later on. Initially, this was done through the command line,
     * but due to some instabilities, this approach is chosen. 
     */
    private function constructFullTextfilePath() {
        return $this->base_outputpath . '/' . 'temptextfile.txt';
    }

    /**
     * This function insert data into the textfile which will be used to call Pystyl
     */
    private function insertPystylConfigIntoTextfile($full_textfilepath) {

        if (is_file($full_textfilepath)) {
            throw new \Exception('stylometricanalysis-error-internal');
            //bad error, should be reported.. 
        }

        $textfile = fopen($full_textfilepath, 'w');
        fwrite($textfile, json_encode($this->pystyl_config));
        fclose($textfile);

        if (!is_file($full_textfilepath)) {
            throw new \Exception('stylometricanalysis-error-internal');
        }

        return true;
    }

    private function deleteTextfile($full_textfilepath) {

        if (!is_file($full_textfilepath)) {
            return true;
        }

        unlink($full_textfilepath);

        if (is_file($full_textfilepath)) {
            throw new \Exception('stylometricanalysis-error-internal');
        }
    }

    /**
     * This function calls Pystyl through the command line
     */
    private function callPystyl($command, $full_textfilepath) {
        $full_textfilepath = "'$full_textfilepath'";
        return system(escapeshellcmd($command . ' ' . $full_textfilepath));
    }

    private function checkPystylOutput($output) {

        //something went wrong when importing data into PyStyl
        if (strpos($output, 'stylometricanalysis-error-import') !== false) {
            throw new \Exception('stylometricanalysis-error-import');
        }

        //the path already exists
        if (strpos($output, 'stylometricanalysis-error-path') !== false) {
            throw new \Exception('stylometricanalysis-error-path');
        }

        //something went wrong when doing the analysis in PyStyl
        if (strpos($output, 'stylometricanalysis-error-analysis') !== false) {
            throw new \Exception('stylometricanalysis-error-analysis');
        }

        if (!is_file($this->full_outputpath1) || !is_file($this->full_outputpath2)) {
            throw new \Exception('stylometricanalysis-error-internal');
        }

        return true;
    }

    private function updateDatabase($time = 0, $new_page_url, $date, $full_linkpath1, $full_linkpath2) {
        $database_wrapper = new StylometricAnalysisWrapper($this->user_name);
        $database_wrapper->clearOldPystylOutput($time);
        $database_wrapper->storeTempStylometricAnalysis($this->collection_name_data, $time, $new_page_url, $date, $full_linkpath1, $full_linkpath2, $this->full_outputpath1, $this->full_outputpath2, $this->pystyl_config);
        return true;
    }

    private function getUserCollectionData() {
        $database_wrapper = new StylometricAnalysisWrapper($this->user_name);
        return $database_wrapper->getManuscriptsCollectionData($this->minimum_pages_per_collection, $this->minimum_collections);
    }

    private function transferDatabaseDataAndGetNewPageUrl($time = 0) {
        $database_wrapper = new StylometricAnalysisWrapper($this->user_name);
        $database_wrapper->transferDataFromTempStylometricAnalysisToStylometricAnalysisTable($time);
        return $database_wrapper->getNewPageUrl($time);
    }

    private function handleExceptions(Exception $exception_error) {

        $error_identifier = $this->error_message = $exception_error->getMessage();
        $error_message = $this->msg($error_identifier);
        $viewer = new StylometricAnalysisViewer($this->getOutput());

        if ($error_identifier === 'error-nopermission') {
            return $viewer->showNoPermissionError($error_message);
        }

        if ($error_identifier === 'stylometricanalysis-error-fewcollections') {
            return $viewer->showFewCollectionsError($error_message);
        }

        if ($this->form === 'Form2' && isset($this->collection_data) && isset($this->collection_name_data)) {
            return $viewer->showForm2($this->collection_data, $this->collection_name_data, $this->getContext(), $error_message);
        }

        return $this->getForm1($error_message);
    }

    /**
     * Callback function. Makes sure the page is redisplayed in case there was an error in Form 2 
     */
    static function callbackForm2($form_data) {
        return false;
    }

}
