<?php

//php phpunit.php C:\xampp\htdocs\mediawikinew\w\extensions\StylometricAnalysis\tests\phpunit\specials
//https://jtreminio.com/2013/03/unit-testing-tutorial-part-3-testing-protected-private-methods-coverage-reports-and-crap/

//check how exceptions are handled

class SpecialStylometricAnalysisTest extends MediaWikiTestCase {

    private $sa;
    private $context; 

    protected function setUp() {
        parent::setUp();
        
        $faux_user = User::newFromName('Root');
        $this->context = new RequestContext;
        $this->context->setUser($faux_user);
        
        $this->sa = $this->mockStylometricAnalysis();
        $this->sa->setContext($this->context);
        $faux_title = Title::newFromText('StylometricAnalysis');
        $this->context->setTitle($faux_title);

    }

    protected function tearDown() {
        unset($this->sa);
        parent::tearDown();
    }

    private function mockStylometricAnalysis() {
        //create a mock object, and make the checkEditToken method a stub
        //callPystyl,, checkPystylOutput,, updateDatabase,,showResult
        $mockStylometricAnalysis = $this->getMockBuilder('SpecialStylometricAnalysis')
            ->setConstructorArgs(array())
            ->setMethods(array('checkEditToken'))
            ->getMock();
        
        return $mockStylometricAnalysis;
    }

    public function testFakeForm1() {
        $fake_formdata = $this->getFakeForm1Data();
        $this->setRequest($fake_formdata);
        $this->assertEquals($this->sa->execute(), true);
    }

//    public function testFakeForm2() {
//        $fake_formdata = $this->getFakeForm2Data();
//        $this->setRequest($fake_formdata);
//        $output = $this->sa->execute();
//        echo 'HERE IS YOUR PYSTYL OUTPUT' . $output . 'ERROR MESSAGE' . $this->sa->error_message;
//        $this->assertEquals($output, 1);
//    }

    public function testSavePageWasRequestedTrue() {
        $fake_savepageArray =  array('save_current_page' => json_encode(array('save_current_page','full_outputpath1', 'full_outputpath2')));
        $this->setRequest($fake_savepageArray);
        $this->assertEquals($this->invokeMethod($this->sa,'savePageWasRequested'),true);
    }

    private function setRequest($data) {
        $faux_request = new FauxRequest($data, true);
        $this->context->setRequest($faux_request);
    }

    private function getFakeForm1Data(){
        
        return array(
            'form1Posted' => 'form1Posted',
            'collection0' => '{"0":"Manuscripts:Root\/test1","1":"Manuscripts:Root\/testpage2","2":"Manuscripts:Root\/testpage3","collection_name":"collection1"}',
            'collection1' => '{"0":"Manuscripts:Root\/test2","1":"Manuscripts:Root\/bla","2":"Manuscripts:Root\/bla2","collection_name":"collection2"}',
        );
        
    }
    
    private function getFakeForm2Data() {

        return array(
          'wptokenizer' => 'whitespace',
          'wpminimumsize' => '0',
          'wpmaximumsize' => '10000',
          'wpsegmentsize' => '0',
          'wpstepsize' => '0',
          'wpvectorspace' => 'tf',
          'wpfeaturetype' => 'word',
          'wpngramsize' => '1',
          'wpmfi' => '100',
          'wpminimumdf' => '0',
          'wpmaximumdf' => '0.9',
          'wpvisualization1' => 'dendrogram',
          'wpvisualization2' => 'dendrogram',
          'wpEditToken' => '6d4e494aaccbdeeeadea655f447f0d2b+',
          'title' => 'Special:StylometricAnalysis',
          'collection_array' => '{"collection0":{"0":"Manuscripts:Root\/test1","1":"Manuscripts:Root\/testpage2","2":"Manuscripts:Root\/testpage3","collection_name":"collection1"},"collection1":{"0":"Manuscripts:Root\/test2","1":"Manuscripts:Root\/bla","2":"Manuscripts:Root\/bla2","collection_name":"collection2"}}',
          'form2Posted' => 'form2Posted',
        );
    }
}