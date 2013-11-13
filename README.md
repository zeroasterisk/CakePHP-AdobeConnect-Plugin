# Adobe Connect Datasource+Models Plugin for CakePHP

* [Adobe Connect](http://www.adobe.com/products/adobeconnect.html) (version 7.5 tested)
* [CakePHP](http://cakephp.org) (version 2.x - branch for 1.3)
* PHP 5.3+

This CakePHP Plugin uses the [Adobe Connect API](http://help.adobe.com/en_US/AcrobatConnectPro/7.5/WebServices/)
as a Datasouce and packages access to it's parts as isolated Models.

* *AdobeConnectPrincipal* - access to all Principals: users & groups
* *AdobeConnectSco* - access to all SCO items: meetings, content, folders, etc.
* *AdobeConnectPermission* - access to Permissions, reading and granting rights: meetings, content, folders, etc.
* *AdobeConnectReport* - access to all Reporting functions: meetings, content, folders, etc.

The API interaction and standard "heavy-lifting" is done in the Datasource, but the Models tie the peices together and facilitate access through normal commands and syntax.

See the unit tests for more information on how to use.

## Installation [--> app/Plugin/AdobeConnect]

If you're using git, you can include this as a submodule in your repository:

    git submodule add https://github.com/zeroasterisk/CakePHP-AdobeConnect-Plugin.git app/Plugin/AdobeConnect
    git submodule update --init --recursive

Or you can clone the repository (but don't do this inside of another repository):

    git clone https://github.com/zeroasterisk/CakePHP-AdobeConnect-Plugin.git app/Plugin/AdobeConnect

## Configuration

   cd app
   cp Plugin/AdobeConnect/Config/adobe_connect.example.php Config/adobe_connect.php
   edit Config/adobe_connect.php

## Usage

You can specify any of the Models in your controller's $uses array:

    public $uses = array(
        'AdobeConnect.AdobeConnectSco',
        'AdobeConnect.AdobeConnectPrincipal',
        'AdobeConnect.AdobeConnectPermission',
        'AdobeConnect.AdobeConnectReport',
    );

Or you can initialize the model, like any other Model:

    if (!isset($this->AdobeConnectSco)) {
        App::uses('AdobeConnectSco', 'AdobeConnect.Model');
        $this->AdobeConnectSco = ClassRegistry::init('AdobeConnect.AdobeConnectSco');
        // just in case you want the config
        $adobeConnectConfig = $this->AdobeConnectSco->config();
    }


Once initilized, you can use it like any other Model.  There are several exposed custom find methods, and custom save/delete functions as well.

    $this->AdobeConnectSco->save();
    $this->AdobeConnectSco->delete();
    $this->AdobeConnectSco->find('search', 'my meeting');
    $this->AdobeConnectSco->find('search', array('conditions' => array('name' => 'my meeting')));
    $this->AdobeConnectSco->find('search', array('conditions' => array('name' => 'my meeting', 'type' => 'meeting')));
    $this->AdobeConnectSco->find('search', array('conditions' => array('name' => 'my*meeting', 'type' => 'meeting')));
    $this->AdobeConnectSco->find('contents', 12345);
    $this->AdobeConnectSco->find('contents', array('sco-id' => 12345, 'conditions' => array('icon' => 'archive')));
    $this->AdobeConnectSco->find('searchcontent', 'welcome training');
    $this->AdobeConnectSco->find('searchcontent', array('query' => 'welcome training', 'conditions' => array('type' => 'content')));
    $this->AdobeConnectSco->find('path', $sco_id);
    $this->AdobeConnectSco->move($sco_id, $folder_id);
    $this->AdobeConnectPrincipal->save();
    $this->AdobeConnectPrincipal->delete();
    $this->AdobeConnectPrincipal->find('search', 'my login');
    @$this->AdobeConnectPrincipal->find('search', array('conditions' => array('email' => 'myemaildomain.com')));
    $this->AdobeConnectPermission->get($sco_id, $principal_id);
    $this->AdobeConnectPermission->assign($sco_id, $principal_id, "view");
    $this->AdobeConnectReport->find("active");
    $this->AdobeConnectReport->find("bulkconsolidatedtransactions", array('conditions' => array('sco-id' => $scoId, 'principal-id' => $principalId), 'limit' => 100));
    $this->AdobeConnectReport->find("coursestatus", $scoId);
    $this->AdobeConnectReport->find("meetingattendance", $scoId);
    $this->AdobeConnectReport->find("meetingconcurrentusers", $scoId);

There are a whole lot more, look at the test cases or the comments in the code...

## Thanks

Big Props to [Neil Crookes](http://www.neilcrookes.com/) and his [CakePHP-GData-Plugin](https://github.com/neilcrookes/CakePHP-GData-Plugin/) which I heard him present on at [cakefest 2010](http://tv.cakephp.org/video/CakeFoundation/2010/12/24/neil_crookes_-_designing_cakephp_plugins_for_consuming_apis)

Also thanks to [Nick Baker](http://webtechnick.com/) for some debugging help.
