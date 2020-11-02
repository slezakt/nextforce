<?php

/**
 * 
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class WorkingController extends Zend_Controller_Action
{
    
    /**
	 *	Zatim jen vice mene pro testovaci ucely, vytvori objekt pravni poradny.
	 */
	public function createconsultingAction()
	{
        Zend_Loader::loadClass('Ibulletin_Content_Consulting');
        
		$consulting = new Ibulletin_Content_Consulting();
		$consulting->annotation = "";
		$consulting->name = "Právní poradna";
		$consulting->tpl_name = "consulting_1.phtml";
		$consulting->html[0] = '
		<div class="pravniporadna">
			<div class="poradnaL">
				<img src="pub/img/michalaPickova.jpg" alt="Michala Picková" />
			</div>
			<div class="poradnaR">
				<p>Odpovídá Vám Mgr. Michala Picková<br />
                právnička KMVS, advokátní kancelář, s. r. o.</p>
                <img src="pub/img/kmvs.png" alt="K V M S advokátní kancelář s.r.o (law office)" />
			</div>
			<div class="reset"></div>
		</div>
		<div class="jakpostupovat">
			<h2>Jak postupovat?</h2>
			<ul>
				<li>Váš dotaz vepište do formuláře.</li>
			<li>Odpověď zašleme cca do dvou týdnů na Váš e-mail.</li>
			<li>Poté se dotaz bez vašich osobních údajů či jiných znaků, které by mohly vést k vaší identifikaci, zobrazí i s odpovědí v rubrice Zodpovězené dotazy.</li>
			<li>Prosíme, pokládejte pouze dotazy, které souvisí s vaší praxí nebo které mohou zajímat i vaše kolegy.</li>
			<li>Zasláním dotazu nevzniká automaticky právní nárok na jeho vyřízení. S ohledem na povahu dotazu, nebo velké množství dotazů si provozovatel vyhrazuje právo nezajistit odpověď na úplně všechny otázky.</li>
			</ul>
		</div>';

		$consulting->form = '
		<div class="dotaz">
			<h2>Položte dotaz:</h2>	
            <div class="dotaz-in">
			<form action="" method="post">
				<fieldset>
					<table border="0" cellspacing="0" cellpadding="0" rules="none" class="table1">
					<tr>
					  <th><label for="dotaz">Váš dotaz:</label></th>
					  <td colspan="2" class="td2"><textarea name="dotaz" id="dotaz" rows="8" cols="65"><?=$this->question?></textarea></td>
					</tr>
				</table>
                <table border="0" cellspacing="0" cellpadding="0" rules="none" class="table2">
					<tr>
                        <th>
                            <label for="email">E-mail, na který si přejete zaslat odpověď:</label>
                        </th>
						<td>
                            <input type="text" class="email" name="email" id="email" value="<?=$this->email?>" />
                        </td>
					</tr>
                    <tr>
                        <th></th>
						<td class="td2"><input type="checkbox" class="souhlas" name="show" id="souhlas" checked="checked"/><label for="souhlas">Souhlasím se zveřeněním dotazu a odpovědi</label></td>
					</tr>
					<tr>
                        <th></th>
                        <td colspan="2" class="td3">
                            <button type="submit">Položit dotaz</button>
                        </td>
					</tr>

                    <!--
						<tr>
							<th><label for="kod">Váš e-mail:</label></th>
							<td><input type="text" class="email" name="email"
							id="email" value="<?=$this->email?>" /></td>
						</tr>	
						<tr>
							<th><label for="">Váš dotaz:</label></th>	
							<td><textarea name="dotaz" id="dotaz" rows="8" cols="60"><?=$this->question?></textarea></td>
						</tr>
						<tr>
							<td colspan="2" class="td3"><button
							type="submit">Položit dotaz</button></td>
						</tr>
                        -->
                        
					</table>
				</fieldset>	
			</form >
            </div>
		</div >
		';

		$consulting->id = 8;
        Zend_Loader::loadClass('Zend_Db_Expr');
		$data = array(
			'changed' => new Zend_Db_Expr('current_timestamp'),
			'serialized_object' => serialize($consulting),
			'class_name' => 'Ibulletin_Content_Consulting'
		);
		try
		{
			$db = Zend_Registry::get('db');
            $q = "SELECT id FROM content WHERE class_name='Ibulletin_Content_Consulting'";
            $id = $db->fetchOne($q);
            
            if(!is_numeric($id)){
                $db->insert('content', $data);
                $id = $db->lastInsertId('content', 'id');
                $consulting->id = $id;
            }
			$data['serialized_object'] = addslashes(serialize($consulting));
			$db->update('content', $data, "id = $id");
            
            
            // Vytvorime zaznamy s defaultnimi hodnotami v content_pages,
            // pages a links pokud neexistuji
            $q = "SELECT 1 FROM content_pages WHERE content_id = $id";
            $page_exists = $db->fetchOne($q);
            if(!$page_exists){
                $config =  Zend_Registry::get('config');
                $config_def_page = $config->admin_default_page_for_content;
                
                $cont_data['obj'] = $consulting;
                
                $name = $cont_data['obj']->getName();
                // Jmeno ma maximalni povolenou delku v pages a links 100 znaku
                $name = substr($name, 0, 100);
                
                // Pridame zaznam do pages
                $ins_data = array('tpl_file' => $config_def_page->tpl_file,
                                  'name' => $name);
                $db->insert('pages', $ins_data);
                $page_id = $db->lastInsertId('pages', 'id');
                
                // Pridame zaznam do links
                $ins_data = array('page_id' => $page_id,
                                  'name' => $name);
                $db->insert('links', $ins_data);
                
                // Pridame zaznam do content_pages
                $ins_data = array('page_id' => $page_id,
                                  'content_id' => $id,
                                  'position' => $config_def_page->position);
                $db->insert('content_pages', $ins_data);
            }
                
		}
		catch (Exception $e)
		{
			echo $e->getMessage();
		}
        
        // Nastavime render script
        $this->getHelper('viewRenderer')->setScriptAction('index');
	}
	
	
	
	
	
	/**
	 * 
	 */
	public function runindircallsAction()
	{
	    $comm = Communicator_ClientAbstract::factory();
	    unset($comm);
	    
	    // Nastavime render script
        $this->getHelper('viewRenderer')->setScriptAction('index');
	}

}
