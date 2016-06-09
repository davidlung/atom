<?php

/*
 * This file is part of the Access to Memory (AtoM) software.
 *
 * Access to Memory (AtoM) is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Access to Memory (AtoM) is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Access to Memory (AtoM).  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Action Handler for FullWidth TreeView
 *
 * @package AccesstoMemory
 * @subpackage model
 * @author Andy Koch <koch.andy@gmail.com>
 */
class InformationObjectFullWidthTreeViewAction extends sfAction
{
  public function execute($request)
  {
    $data = array();

    $this->setTemplate(false);
    $this->setLayout(false);
    $this->resource = $request->getAttribute('sf_route')->resource;
    $this->getResponse()->setContentType('application/json');

    // Viewing a description with a restricted level of description (folder, item or part)
    // the treeview will expand to and select the first non restricted ancestor
    $this->selectedItemId = $this->resource->id;

    if ($request->getParameter('id') == '#') {
      $data = $this->getItemIds($this->resource->getCollectionRoot()->id, !$this->getUser()->user, true);
    } else {
      $data = $this->getItemIds($request->getParameter('id'), !$this->getUser()->user);
    }

    // Alias and pass in $this as $_this because php 5.3 dosn't support
    // referencing $this in anonymouns functions (fixed in php5.4)
    $_this =& $this;

    array_walk($data, function(&$data) use ($_this)
    {
      // Overwrite source culture title if the current culture title is populated
      if ($this->getUser()->getCulture() != $data['source_culture']
        && !empty($data['current_title']))
      {
        $data['text'] = $data['current_title'];
      }

      $data['a_attr']['title'] = $data['text'];
      $data['text'] = ((int) $data['status_id'] == QubitTerm::PUBLICATION_STATUS_DRAFT_ID ? '('.$data['status'].') ' : '') . "<u>{$data['lod']}</u> {$data['text']}";

      // Some special flags on our current selected item
      if ($data['id'] == $_this->selectedItemId)
      {
        $data['state'] = array('opened' => true, 'selected' => true);
        $data['li_attr'] = array('selected_on_load' => true);
      }

      // Set root item's parent to hash symbol for jstree compatibility
      if ($data['parent'] == '1')
      {
        $data['parent'] = '#';
        $data['icon'] = 'fa fa-archive';
        unset($data['children']);
      }
      
      if ($data['children'] == '1')
      {
        $data['children'] = true;
      }
      else
      {
        unset($data['children']);
      }

      $data['a_attr']['href'] = $_this->generateUrl('slug', array('slug' => @$data['slug']));

      // Not used currently
      unset($data['status'], $data['status_id'], $data['slug'], $data['source_culture'], $data['current_title']);
    });

    return $this->renderText(json_encode($data));
  }

protected function getItemIds($itemIdParam, $drafts = true, $parent = false)
{
 	error_log($itemIdParam);
    $delimiterPos = strpos($itemIdParam, "#");
    $groupNode = ($delimiterPos !== false) ? true : false;
    $groupNr = "";
    
    $itemId = $itemIdParam;
    if ($groupNode) {
    	$itemId = substr($itemIdParam, 0, $delimiterPos);
    	$groupNr = substr($itemIdParam, ($delimiterPos+1));
    }
    
    $item = QubitInformationObject::getById($itemId);
    $i18n = sfContext::getInstance()->i18n;
    $untitled = $i18n->__('Untitled');
    
  	$ids = 0;
  	
    $conn = Propel::getConnection();
  	$stmt = $conn->prepare("select count(io.id) from information_object io where io.parent_id = :id;");
  	$stmt->bindValue(':id', $item->id, PDO::PARAM_INT);
  	$stmt->execute();
  	$ids = $stmt->fetchColumn();
  	
    if (!$groupNode && $ids > 100)
    {
      $result = array();
      if ($parent) {
      	array_push($result, array('id'=>$item->id, 'text'=>$item->title, 'parent'=>'#', 'slug'=>$item->slug));
      }
      for ($i = 0; $i < ceil($ids / 100); $i++) {
      	$from = $i*100+1;
      	$to = ($i+1)*100;
      	if (($i+1) == ceil($ids / 100)) {
      		$to = $ids;
      	}
      	$tmpGroupNr = $from.'-'.$to;
      	$id = $item->id . '#' . $tmpGroupNr;
      	array_push($result, array('slug'=>$item->slug, 'groupNode'=>true, 'id'=>$id, 'parent'=>$item->id, 'groupNr'=>$tmpGroupNr, 'text' => $tmpGroupNr, 'children' => true));
      }
      return $result;
    }
    
    $idPart = '';
    $limitFrom = 0;
    $limitTo = 100;
    
    if ($groupNode) {
    $idPart = $idPart.'#'.$groupNr;
    $limitFrom = ((int)substr($groupNr, 0, strpos($groupNr, '-')))-1;
    $limitTo   = ((int)substr($groupNr, strpos($groupNr, '-')+1))-1;
    }
    
  	
    $draftsSql = ($drafts) ? " AND status.status_id <> " . QubitTerm::PUBLICATION_STATUS_DRAFT_ID . " " : " " ;
    
    $parentSql = ($parent) ? " OR (io.id = $item->id) " : " ";

    $sql = "SELECT
        io.id,
        io.source_culture,
        current_i18n.title AS current_title,
        IFNULL(source_i18n.title, '<i>$untitled</i>') as text,
        CONCAT(io.parent_id, '$idPart')  AS parent,
        slug.slug,
        IFNULL(lod.name, '') AS lod,
        st_i18n.name AS status,
        status.status_id AS status_id,
        CASE WHEN EXISTS (SELECT c.id FROM information_object c WHERE c.parent_id = io.id LIMIT 1)
          THEN 1
          ELSE 0
        END AS children
        FROM
          information_object io
          LEFT JOIN information_object_i18n current_i18n ON io.id = current_i18n.id AND current_i18n.culture = :culture
          LEFT JOIN information_object_i18n source_i18n ON io.id = source_i18n.id AND source_i18n.culture = io.source_culture
          LEFT JOIN term_i18n lod ON io.level_of_description_id = lod.id AND lod.culture = :culture
          LEFT JOIN status ON io.id = status.object_id AND status.type_id = :pubStatus
          LEFT JOIN term_i18n st_i18n ON status.status_id = st_i18n.id AND st_i18n.culture = :culture
          LEFT JOIN slug ON io.id = slug.object_id
        WHERE
          (io.parent_id = $item->id) 
          $parentSql
          $draftsSql
        ORDER BY io.lft
        LIMIT $limitFrom, $limitTo;";

    $conn = Propel::getConnection();

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':culture', $this->getUser()->getCulture());
    $stmt->bindValue(':pubStatus', QubitTerm::STATUS_TYPE_PUBLICATION_ID);
    $stmt->execute();
 
    $ids = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $ids;
  }
}
