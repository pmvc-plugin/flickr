<?php
namespace PMVC\PlugIn\flickr;

${_INIT_CONFIG}[_CLASS] = __NAMESPACE__.'\flickr';

class flickr extends \PMVC\PlugIn
{
    public function init()
    {
        \PMVC\l(__DIR__.'/src/FlickrApi.php');
        $flickr = new FlickrApi($this['appId'],$this['appSecret'],$this['callBack']);
        $this->setDefaultAlias($flickr);
    }
}
