<?php 
class ProcessamentoController extends AppController{
	
	var $uses 		= array('Imovel','ImovelFoto', 'ImovelCarac', 'Foto', 'Empreendimento', 'Cidade', 'Bairro');
	var $components = array('Util');
	var $helpers 	= array();
	
	
	function initalize($countImovel, $countFoto){
		
		$imovel = $this->Imovel->find('first', array('order'=>array('cd_imovel'=>'DESC')));
		if(empty($imovel)) 
			$countImovel = 0;
		else
			$countImovel = $imovel['Imovel']['cd_imovel'];
		
		
		$foto = $this->Foto->find('first', array('order'=>array('cd_foto'=>'DESC')));
		if(empty($foto)) 
			$countFoto = 0;
		else
			$countFoto = $foto['Foto']['cd_foto'];
		
	}
	
	function index(){
		
		list($usec, $sec) = explode(' ', microtime());
		$script_start = (float) $sec + (float) $usec;
		
		
		$clientes = ClassRegistry::init('Cliente')->findAll();
		foreach($clientes as $c){
			
			$path = PATH_TO_FILES.$c['Cliente']['slug_cliente'].DS.'xml'.DS.'toProcess';
			
			if(($files = scandir($path)) && (count($files) > 2)){

				/*
				 * Troca de contexto.
				 */
				$db = ConnectionManager::getDataSource("default");
				$db->reconnect(array(
					'host' 		=> $c['Cliente']['db_host'],
					'login' 	=> $c['Cliente']['db_user'],
					'password' 	=> $c['Cliente']['db_pass'],
					'database' 	=> $c['Cliente']['db_name'],
				));
				
				#debug($db);

				
				/*
				 * Contadores e Variaveis
				 * */
				$countImovel; $countFoto;
				$this->initalize(&$countImovel, &$countFoto);
				$processados = array(
					'Imovel' 	 => array(),
					'ImovelFoto' => array(),
				);
				
				
				
				for($j=2; $j<count($files); $j++){

					$carga = $this->readXml($path.DS.$files[$j]);
					foreach($carga['Imoveis'][0]['Imovel'] as $imovel){
						
						
						#echo $imovel['Cidade'].' - '.$imovel['Bairro'].'<br>';
						
						if(isset($imovel['Fotos']) && count($imovel['Fotos']) > 0)
						$this->process($imovel, &$countImovel, &$countFoto, &$processados);						
					}
					
					if(count($processados['Imovel']) > 0){
						
						/*
						 * Verifica se todos os imoveis foram deletados.
						 * 
						 * Seleciona todos os imoveis do banco de dados, e os bate com cada um dos imoveis processados
						 * se a referencia bater, remove do array dos imoveis encontrados no BD .
						 * 
						 * */
						$inBase = $this->Imovel->find('all', array('recursive' => -1));
						if(count($inBase) > 0)
						foreach($processados['Imovel'] as $new){
							foreach($inBase as $key => $imovel){
								if($imovel['Imovel']['cd_ref'] == $new['Imovel']['cd_ref']){
									unset($inBase[$key]);
									break;
								}
							}
						}
						
						/*
						 * Os imoveis restantes no array de imoveis encontrados não estão presentes 
						 * no array de imoveis processados, por tanto foram deletados do Gaia.
						 * */
						if(count($inBase) > 0) foreach($inBase as $toDelete) $this->removeImovel($toDelete);

						/*
						 * Salvando e atualizando todos os imóveis processados.
						 * */
						$this->Imovel->saveAll($processados['Imovel']);
					} 	
					/*
					 * Salvando todos os vinculos de Imovel > Foto.
					 * alterado: 16/04, inserido no meio do processamento de fotos.
					 * */
					#if(count($processados['ImovelFoto']) > 0) $this->ImovelFoto->saveAll($processados['ImovelFoto']);
					
					/*
					 * Deletando o arquivo a ser processado.
					 * */
					unlink($path.DS.$files[$j]);
				}
			}
		}
		
		
		/*
		 * LOG FINAL
		 * */
		list($usec, $sec) = explode(' ', microtime());
		$script_end = (float) $sec + (float) $usec;
		$elapsed_time = round($script_end - $script_start, 5);
		
		$mensagem = "Executado com sucesso. \n\n";
		$mensagem.= 'Tempo de Execucao: '.$elapsed_time.' secs. Pico de memoria: '.round(((memory_get_peak_usage(true) / 1024) / 1024), 2).' mb';
		
		require_once(ROOT.DS.APP_DIR.DS.'mail'.DS.'mail.php');
		
		$smtp = Mail::factory('smtp',
		 array (
		 'host' 	=> "mail.XXX.com.br",
		 'auth' 	=> true,
		 'username' => "no-reply@XXX.com.br",
		 'password' => "XXX",
		 'port'		=> 587
		));
		$headers = array (
		 'From' 	=> "no-reply@XXX.com.br",
		 'To' 		=> MAIL_LOG,
		 'Subject' 	=> "Int.Gaia - Processamento ".date("d/m/y")
		);
		$mail = $smtp->send(MAIL_LOG, $headers, $mensagem);
		
		#if (PEAR::isError($mail)) {
		# echo "Erro : ". $mail->getMessage();
		#}
		
		echo "<script>
			ww = window.open(window.location, '_self');
			ww.close();
		</script>";
		exit;
	}
	
	
	function process($imovel, $countImovel, $countFoto, $processados){
		
		$continue = FALSE;
		
		/*
		 * PASSO 1 - MONTAR ESTRUTURA
		 * */
		$countImovel++;
		$processados['Imovel'][$countImovel] = array(
			'Imovel' => array(
				'cd_imovel'		=> $countImovel,
				'cd_ref' 		=> @$imovel['CodigoImovel'],
				'nome' 	 		=> @$imovel['TituloImovel'],
				'endereco' 		=> @$imovel['Endereco'],
				'numero' 		=> @$imovel['Numero'],
				'cep'			=> @$imovel['CEP'],
				'salas'			=> @$imovel['QtdSalas'],
				'vagas'			=> @$imovel['QtdVagas'],
				'dormitorios'	=> @$imovel['QtdDormitorios'],
				'suites'		=> @$imovel['QtdSuites'],
				'area_util'		=> @$imovel['AreaUtil'],
				'area_total'	=> @$imovel['AreaTotal'],
				'valor'			=> @$imovel['PrecoVenda'],
				'valor_aluguel'	=> @$imovel['PrecoLocacao'],
				'condominio'	=> @$imovel['PrecoCondominio'],
				'descricao'		=> @$imovel['Observacao'],
		));
	
		/*
		 * Recuperando Empreendimento (Categoria) 
		 * */
		$empreendimento = $this->Empreendimento->find('first', array(
			'conditions' => array(
				"nm_empreendimento LIKE '%".$imovel['TipoImovel']."%'"
			)
		));
			
		if(!empty($empreendimento)) 
			$processados['Imovel'][$countImovel]['Imovel']['cd_empreendimento'] = $empreendimento['Empreendimento']['cd_empreendimento'];
			
			
		/*
		 * Recuperando Cidade e Bairro
		 * */
		$imovel['Bairro'] = str_replace(array('(',')'), array(' - ', ''), $imovel['Bairro']);
		$cidade = $this->Cidade->find('first', array(
			'conditions' => array(
				"nm_cidade LIKE '%".$imovel['Cidade']."%'"
			)
		));
		
		if(!empty($cidade)){
			
			$processados['Imovel'][$countImovel]['Imovel']['cd_cidade'] = $cidade['Cidade']['cd_cidade'];
			
			$bairro = $this->Bairro->find('first', array(
				'conditions' => array(
					'Bairro.cd_cidade' => $cidade['Cidade']['cd_cidade'],
					"Bairro.slug_bairro LIKE '%".$this->Util->slugfy($imovel['Bairro'])."%'"
				)
			));
			
			if(!empty($bairro)){
			
				$processados['Imovel'][$countImovel]['Imovel']['cd_bairro'] = $bairro['Bairro']['cd_bairro'];
			
			}else{#Cadastrando o Bairro
				
				$bairro = array(
					'Bairro' => array(
						'cd_cidade' => $cidade['Cidade']['cd_cidade'],
						'nm_bairro' => $imovel['Bairro'],
						'slug_bairro' => $this->Util->slugfy($imovel['Bairro'])
					)
				);
				$this->Bairro->create();
				$this->Bairro->save($bairro);
				$bairro = $this->Bairro->find('first', array(
					'conditions' => array(
						'Bairro.cd_cidade' => $cidade['Cidade']['cd_cidade'],
						"Bairro.nm_bairro LIKE '%".$imovel['Bairro']."%'"
					)
				));
			
				$processados['Imovel'][$countImovel]['Imovel']['cd_bairro'] = $bairro['Bairro']['cd_bairro'];
				
			}
			
		}else{#Cadastrando Cidade e Bairro
			
			$cidade = array(
				'Cidade' => array(
					'nm_cidade' => $imovel['Cidade'],
					'slug_cidade' => $this->Util->slugfy($imovel['Cidade'])
				)
			);
			
			$this->Cidade->create();
			$this->Cidade->save($cidade);
			$cidade = $this->Cidade->find('first', array(
				'conditions' => array(
					"nm_cidade LIKE '%".$imovel['Cidade']."%'"
				)
			));
			
			$processados['Imovel'][$countImovel]['Imovel']['cd_cidade'] = $cidade['Cidade']['cd_cidade'];
		
			
			$bairro = array(
				'Bairro' => array(
					'cd_cidade' => $cidade['Cidade']['cd_cidade'],
					'nm_bairro' => $imovel['Bairro'],
					'slug_bairro' => $this->Util->slugfy($imovel['Bairro'])
				)
			);
			
			$this->Bairro->create();
			$this->Bairro->save($bairro);
			$bairro = $this->Bairro->find('first', array(
				'conditions' => array(
					'Bairro.cd_cidade' => $cidade['Cidade']['cd_cidade'],
					"Bairro.nm_bairro LIKE '%".$imovel['Bairro']."%'"
				)
			));
			
			$processados['Imovel'][$countImovel]['Imovel']['cd_bairro'] = $bairro['Bairro']['cd_bairro'];
		}
		
		
		/*
		 * PASSO 2 - VERIFICANDO ATUALIZACAO 
		 **/
		
		
		/*
		 * Verificando se o imovel ja esta adicionado.
		 * */
		$updatedImovel = FALSE;
		$inBaseImovel  = $this->Imovel->findByCd_ref($imovel['CodigoImovel']);
		if(!empty($inBaseImovel)){
			
			/*
			 * Se o imóvel já se encontra na base,
			 * atualizamos o cd_imovel, para evitar duplicação
			 * */
			$processados['Imovel'][$countImovel]['Imovel']['cd_imovel'] = $inBaseImovel['Imovel']['cd_imovel'];
			
			/*
			 * Resetamos as Características do Imóvel que serão reprocessadas
			 * "O custo operacional do reprocessamento e menor que o de verificação."
			 * */
			$this->ImovelCarac->query("DELETE FROM imoveiscaracs WHERE cd_imovel = ".$inBaseImovel['Imovel']['cd_imovel']);
			
			/*
			 * Verifica campo por campo
			 * se algum campo for diferente
			 * verificaremos as fotos individualmente posteriormente atraves do "$checkFotos"
			 * */
			foreach($processados['Imovel'][$countImovel]['Imovel'] as $key => $value){
				if($processados['Imovel'][$countImovel]['Imovel'][$key] != $inBaseImovel['Imovel'][$key]){
					$updatedImovel = TRUE;
					break;
				}
			}

			/*
			 * Verificamos o número de fotos que o Imóvel possui
			 * caso a quantidade não bata
			 * verificaremos as fotos individuamente posteriormente atraves do "$checkFotos"
			 * */
			$fotosXML = 0;
			foreach($imovel['Fotos'] as $f) $fotosXML = count($f);
			if(count($this->ImovelFoto->findAllByCd_imovel($inBaseImovel['Imovel']['cd_imovel'])) != $fotosXML){
				$updatedImovel = TRUE;
			}
			
		}
		
		
		$tmp_control = time();
		
		/*
		 * Passo 3 - Processando Imagens
		 * 
		 * Caso seja um novo imóvel  
		 * ou se houveram alterações no imóvel
		 * */
		if(empty($inBaseImovel) || (!empty($inBaseImovel) && $updatedImovel)){
			
			$dirName = $this->Util->slugfy($imovel['TipoImovel'].'-em-'.$imovel['Cidade']);
			$fotos = array();
			
			if($updatedImovel)	$ImovelFotos = $this->ImovelFoto->findAllByCd_imovel($processados['Imovel'][$countImovel]['Imovel']['cd_imovel']);
			else 				$ImovelFotos = array();
			
			foreach($imovel['Fotos'] as $f) foreach($f['Foto'] as $key => $foto){
				
				$insertFoto = TRUE;

				/*
				 * Verificando a foto a ser inserida
				 * com todas as fotos encontradas daquele imóvel.
				 * 
				 * ao encontra-la remove ela do array
				 * para descobrir quais devem ser deletadas
				 * */
				if($updatedImovel){
					foreach($ImovelFotos as $key => $ImovelFoto){
						if($ImovelFoto['Foto']['nome_original'] == $foto['URLArquivo']){
							$insertFoto = FALSE;
							if($foto['Principal'] == 1) $processados['Imovel'][$countImovel]['Imovel']['cd_capa'] = $ImovelFoto['Foto']['cd_foto'];
							unset($ImovelFotos[$key]);
							break;
						}
					}
				}
				
				/*
				 * Inserindo Foto
				 * */
				if($insertFoto){
					$countFoto++;			
					if($foto['Principal'] == 1) $processados['Imovel'][$countImovel]['Imovel']['cd_capa'] = $countFoto;
						
					$fileName = explode('.',$foto['URLArquivo']);
					$fileName = $dirName.'-'.$countFoto.'.'.$fileName[count($fileName)-1];
					
					/*
					 * Salvando nova foto.
					 * */
					$this->Foto->create();
					$foto = array(
						'Foto' => array(
							'cd_foto' => $countFoto,
							'cm_foto' => $fileName,
							'cm_dir'  => $dirName,
							'nome_original' => $foto['URLArquivo'],
							'tmp_control' => $tmp_control
					));
					$this->Foto->save($foto);
					
					
					/*
					 * Salvando novo vinculo.
					 * */
					$this->ImovelFoto->create();
					$imovelfoto = array(
					'ImovelFoto' => array(
						'cd_imovel' => $processados['Imovel'][$countImovel]['Imovel']['cd_imovel'],
						'cd_foto'   => $countFoto
					));
					$this->ImovelFoto->save($imovelfoto);
					
					
					
				}
			}#eof XML->foto

			/*
			 * Verifica quais fotos devem ser deletadas
			 * */
			if(!empty($ImovelFotos) > 0){
				foreach($ImovelFotos as $key => $if) $ImovelFotos[$key]['ImovelFoto']['nm_foto'] = '*remove*';
				$this->ImovelFoto->saveAll($ImovelFotos);
			}
			
		}#eof passo3
		
		/*
		 * Reprocessando características. 
		 * */
		$caracs = array();
		if(isset($imovel["Agua"]))					$caracs[] = '43';
		if(isset($imovel["ArCondicionado"]))		$caracs[] = '44';
		if(isset($imovel["ArmarioCozinha"]))		$caracs[] = '45';
		if(isset($imovel["Churrasqueira"]))			$caracs[] = '26';
		if(isset($imovel["Copa"]))					$caracs[] = '2';
		if(isset($imovel["EntradaCaminhoes"]))		$caracs[] = '46';
		if(isset($imovel["Escritorio"]))			$caracs[] = '47';
		if(isset($imovel["Esgoto"]))				$caracs[] = '48';
		if(isset($imovel["Piscina"]))				$caracs[] = '6';
		if(isset($imovel["PisoElevado"]))			$caracs[] = '49';
		if(isset($imovel["QuadraPoliEsportiva"]))	$caracs[] = '50';
		if(isset($imovel["Quintal"]))				$caracs[] = '51';
		if(isset($imovel["RuaAsfaltada"]))			$caracs[] = '52';
		if(isset($imovel["Sauna"]))					$caracs[] = '53';
		if(isset($imovel["Telefone"]))				$caracs[] = '54';
		if(isset($imovel["TVCabo"]))				$caracs[] = '55';
		if(isset($imovel["Varanda"]))				$caracs[] = '56';
		if(isset($imovel["Vestiario"]))				$caracs[] = '57';
		if(isset($imovel["WCEmpregada"]))			$caracs[] = '3';
		if(isset($imovel["Hidromassagem"]))			$caracs[] = '24';
		if(isset($imovel["FrenteMar"]))				$caracs[] = '4';
		if(isset($imovel["AreaServico"]))			$caracs[] = '1';
		if(isset($imovel["CampoFutebol"]))			$caracs[] = '58';
		if(isset($imovel["Caseiro"]))				$caracs[] = '59';
		if(isset($imovel["Despensa"]))				$caracs[] = '60';
		if(isset($imovel["EnergiaElectrica"]))		$caracs[] = '61';
		if(isset($imovel["Mobiliado"]))				$caracs[] = '62';
		if(count($caracs) > 0){
			foreach($caracs as $c){
				$caracsToSave[] = array(
					'ImovelCarac' => array(
						'cd_imovel' => $processados['Imovel'][$countImovel]['Imovel']['cd_imovel'],
						'cd_carac'  => $c
					)
				);
			}
			$this->ImovelCarac->saveAll($caracsToSave);
		}
	}
	function removeImovel($imovel){
		$this->ImovelFoto->query("UPDATE imoveisfotos SET nm_foto = '*remove*' WHERE cd_imovel = ".$imovel['Imovel']['cd_imovel']);
		$this->Imovel->delete($imovel['Imovel']['cd_imovel']);
	}
	function readXml($path){
		
		$xml = simplexml_load_file($path);
		return $this->xml2array($xml, array());
		
	}
	function xml2array($xml, $arr){
   		$iter = 0;
        foreach($xml->children() as $b){
            $a = $b->getName();
            if(!$b->children()){
                    $arr[$a] = mb_convert_encoding(utf8_decode(trim($b[0])), "ISO-8859-1", "UTF-8");
            }
            else{
                    $arr[$a][$iter] = array();
                    $arr[$a][$iter] = $this->xml2array($b,$arr[$a][$iter]);
            }
        	$iter++;
        }
        return $arr;
	}
	
}?>