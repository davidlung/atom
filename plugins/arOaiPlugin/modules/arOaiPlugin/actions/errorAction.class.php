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
 * Generate the OAI-PMH response
 *
 * @package    AccesstoMemory
 * @subpackage oai
 * @author     Mathieu Fortin Library and Archives Canada <mathieu.fortin@lac-bac.gc.ca>
 */
class arOaiPluginErrorAction extends sfAction
{
  /**
   * Executes action
   *
   * @param sfRequest $request A request object
   */
  public function execute($request)
  {
    sfConfig::set('sf_escaping_strategy', false);

    $request->setRequestFormat('xml');
    $this->date = QubitOai::getDate();
    $this->path = $this->request->getUriPrefix().$this->request->getPathInfo();

    $this->attributes = $this->request->getGetParameters();
    $this->attributesKeys = array_keys($this->attributes);
    $this->requestAttributes = '';
    foreach ($this->attributesKeys as $key)
    {
      $this->requestAttributes .= ' '.$key.'="'.$this->attributes[$key].'"';
    }
  }
}
