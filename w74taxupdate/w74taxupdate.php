<?php
/**
© Web 74 
contributor(s) : Vincent Berthet (13/12/2013)

vincent@web-74.com

This software is governed by the CeCILL license under French law and
abiding by the rules of distribution of free software.  You can  use, 
modify and/ or redistribute the software under the terms of the CeCILL
license as circulated by CEA, CNRS and INRIA at the following URL
"http://www.cecill.info". 

As a counterpart to the access to the source code and  rights to copy,
modify and redistribute granted by the license, users are provided only
with a limited warranty  and the software's author,  the holder of the
economic rights,  and the successive licensors  have only  limited
liability. 

In this respect, the user's attention is drawn to the risks associated
with loading,  using,  modifying and/or developing or reproducing the
software by the user in light of its specific status of free software,
that may mean  that it is complicated to manipulate,  and  that  also
therefore means  that it is reserved for developers  and  experienced
professionals having in-depth computer knowledge. Users are therefore
encouraged to load and test the software's suitability as regards their
requirements in conditions enabling the security of their systems and/or 
data to be ensured and,  more generally, to use and operate it in the 
same conditions as regards security. 

The fact that you are presently reading this means that you have had
knowledge of the CeCILL license and that you accept its terms.
 */

if (!defined('_PS_VERSION_'))
    exit;

class W74TaxUpdate extends Module {

    private $cfg = null;

    public function __construct(){
        $this->name = 'w74taxupdate';
        $this->tab = 'billing_invoicing';
        $this->version = '0.1';
        $this->author = 'Web 74';
        $this->need_instance = 0;
        $this->is_configurable = 1;

        parent::__construct();

        $this->displayName = $this->l('Mise a jour TVA 2014 Automatique');
        $this->description = $this->l('Met à jour automatiquement la TVA le 1er janvier 2014.');
    }

    /**
     * @see ModuleCore::install()
     */
    public function install(){
        return parent::install() && $this->registerHook('displayHeader');
    }

    public function hookHeader($params)
    {
        if(time() < mktime(0,0,1,1,1,2014)){
            return; // Not yet in 2014
        }
        //Begin VAT Update
        Logger::addLog("Begin update french tax rule 20104");
        $cfg = $this->getCfg();
        foreach($cfg as $taxId => $newValue){
            try{
                //Update VAT Rule using configuration
                Logger::addLog("Update tax rule id:".$taxId.", Set to : ".$newValue);
                $tax = new Tax($taxId);
                if(isset($newValue["rate"])){
                    $tax->rate = $newValue["rate"];
                }
                if(isset($newValue["name"])){
                    $tax->name = $newValue["name"];
                }
                $tax->update(false,false);
                Logger::addLog("Tax rule id:".$taxId." updated.".$newValue);
            } catch(Exception $e){
                Logger::addLog("Error while Updating Tax rule (id : ".$taxId.") can't set it to ".$newValue." %",4);
            }
        }
        $this->unregisterHook('displayHeader');
    }

    /**
     * Load module configuration
     */
    private function getCfg(){
        $v = unserialize(base64_decode(Configuration::get("W74_AUTO_VAT")));
        return is_array($v) ? $v : Array();
    }

    /**
     * Save module configuration
     */
    private function setCfg($cfg){
        Configuration::updateValue("W74_AUTO_VAT",base64_encode(serialize($cfg)));
    }

    /**
     * Called in administration -> module -> configure
     */
    public function getContent()
    {
        $output = '<h2>'.$this->displayName.'</h2>';
        if (Tools::isSubmit('submitBestSellers'))
        {
            $v = Tools::getValue("autoVatUpdate");
            $name = Tools::getValue("autoVatUpdateName");
            // Merge param array into a unique array
            $v = array_map(function($a,$b,$k){
                if(empty($a)){
                    return Array("name" => $b,"id" => $k);
                } elseif(empty($b)){
                    return Array("rate" => floatval($a),"id" => $k);
                }else{
                    return Array("rate" => floatval($a),"name" => $b,"id" => $k);
                }
            },$v,$name,array_merge(array_keys($v),array_keys($name)));
            // Filter empty values
            $v = array_filter($v,function($i){ return !(empty($i["name"]) && empty($i["rate"]));});
            // rebuild array using id as key
            $cfg = array_reduce($v,function($arr,$a){$arr[$a["id"]] = $a; return $arr;});
            $this->setCfg($cfg);
        }
        return $output.$this->displayForm();
    }

    function displayFormRow(){
        $current_cfg = $this->getCfg();
        // Find all tax rules
        $sql = new DbQuery();
        $sql->select("*");
        $sql->from("tax","t");
        $sql->where('t.`deleted` != 1'); //ignore deleted rules
        $sql->where('t.`active` = 1'); // Only active
        // Load Lang
        $sql->select('tl.name, tl.id_lang');
        $sql->leftJoin('tax_lang', 'tl', 't.`id_tax` = tl.`id_tax` AND tl.`id_lang` = '.(int)Context::getContext()->language);

        $taxes = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

        $r = "";
        foreach($taxes as $tax){
            $r .= "<tr>";
            $r .= '<td>'.$tax["id_tax"].'</td>';
            $r .= '<td>'.$tax["name"].'</td>';
            $r .= '<td>'.$tax["rate"].' % </td>';
            $r .= '<td><input name="autoVatUpdate['.$tax["id_tax"].']" type="text" value="'.$current_cfg[$tax["id_tax"]]["rate"].'" /> % </td>';
            $r .= '<td><input name="autoVatUpdateName['.$tax["id_tax"].']" type="text" value="'.$current_cfg[$tax["id_tax"]]["name"].'" /> % </td>';
            $r .= "</tr>";
        }
        return $r;
    }

    public function displayForm()
    {
        return '
		<form action="'.$_SERVER['REQUEST_URI'].'" method="post">
			<fieldset>
				<legend><img src="'.$this->_path.'logo.gif" alt="" title="" />'.$this->l('Settings').'</legend>
				<table class="table  tax" cellpadding="0" cellspacing="0" style="width: 100%; margin-bottom:10px;">
				    <tbody>
				        <tr>
				            <th>'.$this->l("Id").'</th>
				            <th>'.$this->l("Nom").'</th>
				            <th>'.$this->l("Taux courrant").'</th>
				            <th>'.$this->l("Nouveau taux (laisser vide pour ignorer)").'</th>
				            <th>'.$this->l("Nouveau nom (laisser vide pour ignorer)").'</th>
				        </tr>
                        '.$this->displayFormRow().'
				    </tbody>
				</table>
				<center><input type="submit" name="submitBestSellers" value="'.$this->l('Save').'" class="button" /></center>
			</fieldset>
		</form>';
    }
}
