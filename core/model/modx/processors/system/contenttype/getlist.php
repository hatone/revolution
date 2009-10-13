<?php
/**
 * Gets a list of content types
 *
 * @param integer $start (optional) The record to start at. Defaults to 0.
 * @param integer $limit (optional) The number of records to limit to. Defaults
 * to 10.
 * @param string $sort (optional) The column to sort by. Defaults to name.
 * @param string $dir (optional) The direction of the sort. Defaults to ASC.
 *
 * @package modx
 * @subpackage processors.system.contenttype
 */
if (!$modx->hasPermission('content_types')) return $modx->error->failure($modx->lexicon('permission_denied'));
$modx->lexicon->load('content_type');

/* setup default properties */
$isLimit = !empty($_REQUEST['limit']);
$start = $modx->getOption('start',$_REQUEST,0);
$limit = $modx->getOption('limit',$_REQUEST,10);
$sort = $modx->getOption('sort',$_REQUEST,'name');
$dir = $modx->getOption('dir',$_REQUEST,'ASC');

/* get content types */
$c = $modx->newQuery('modContentType');
$c->sortby($sort,$dir);
if ($isLimit) $c->limit($limit,$start);
$contentTypes = $modx->getCollection('modContentType',$c);
$count = $modx->getCount('modContentType');

$list = array();
foreach ($contentTypes as $contentType) {
    $contentTypeArray = $contentType->toArray();
    $contentTypeArray['menu'] = array(
        array(
            'text' => $modx->lexicon('content_type_remove'),
            'handler' => 'this.confirm.createDelegate(this,["remove","'.$modx->lexicon('content_type_remove_confirm').'"])'
        )
    );
    $list[] = $contentTypeArray;
}
return $this->outputArray($list,$count);