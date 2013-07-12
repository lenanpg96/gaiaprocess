<?php 
define("MAIL_LOG", 'renan@XXX.com.br');

class ValueController extends AppController{
	
	var $uses 		= array('Foto', 'ImovelFoto');
	var $components = array('ImageHandler');
	var $helpers 	= array();
	
	function index(){}
	
	function saveImages(){
		list($usec, $sec) = explode(' ', microtime());
		$script_start = (float) $sec + (float) $usec;
		
		/*
		 * Baixando Imagens
		 * */
		$fotos = $this->Foto->find('all', array(
			'conditions'=>array(
				"tmp_control != ''"  
			)
		));
		if(count($fotos) > 0){
			foreach($fotos as $key => $foto){
			
				if(!is_dir(PATH_TO_FILES.$foto['Foto']['cm_dir'])) mkdir(PATH_TO_FILES.$foto['Foto']['cm_dir']);
				
				$ch = curl_init($foto['Foto']['nome_original']);
				$fp = fopen(PATH_TO_FILES.$foto['Foto']['cm_dir'].DS.$foto['Foto']['cm_foto'], 'wb');
				
				curl_setopt($ch, CURLOPT_FILE, $fp);
				curl_setopt($ch, CURLOPT_HEADER, 0);
				curl_exec($ch);
				curl_close($ch);
				fclose($fp);
				
				$this->Foto->query("UPDATE fotos SET tmp_control = '' WHERE cd_foto = ".$foto['Foto']['cd_foto']);
			}
		}
		
		
		/*
		 * Removendo Fotos
		 * */
		$this->ImovelFoto->unBindMe();
		$ImovelFoto = $this->ImovelFoto->find('all', array(
			'conditions' => array(
				"nm_foto LIKE '%*remove*%'" 
			)
		));
		if(count($ImovelFoto) > 0){
			foreach($ImovelFoto as $if) $this->ImageHandler->fotoDelete($if['ImovelFoto']['cd_foto']);
			
			$this->ImovelFoto->deleteAll(array(
				"nm_foto LIKE '%*remove*%'"
			));
		}
		
		/*
		 * LOG FINAL
		 * */
		list($usec, $sec) = explode(' ', microtime());
		$script_end = (float) $sec + (float) $usec;
		$elapsed_time = round($script_end - $script_start, 5);
		
		$mensagem = "Executado com sucesso. \n\n";
		$mensagem.= 'Tempo de Execucao: '.$elapsed_time.' secs. Pico de memoria: '.round(((memory_get_peak_usage(true) / 1024) / 1024), 2).' mb';
		
		$cab = "To: <".MAIL_LOG.">\r\n";
		$cab.= "From: <no-reply@XXX.com.br>\r\n";
		$cab.= "Reply-To: <no-reply@XXX.com.br>\r\n";
		$cab.= "Return-Path: <no-reply@XXX.com.br>\r\n";

		@mail(MAIL_LOG, "Int.Gaia - Imagens XXX ".date("d/m/y"), $mensagem,$cab);
		
		#if (PEAR::isError($mail)) {
		# echo "Erro : ". $mail->getMessage();
		#}
		
		/*
		 * Caso mais de uma foto inserida, atualiza hotsites da imobiliária.
		 * */
		if(count($fotos) > 0){
			echo "<html>
				<body onload='abre()'>
					<script>
						function closeMe(){
							ww = window.open(window.location, '_self');
							ww.close();
						}
						".'function abre(){
							window.open ("http://www.XXX.cim.br/p/getImages","mywindow","status=1");
							window.open ("http://www.XXX.net.br/p/getImages","mywindow2","status=1");
							window.open ("http://www.XXX.com.br/p/getImages","mywindow3","status=1");
							
							setTimeout(4000, closeMe());
						}'."
					</script>
				</body>
			</html>
			"; 
		}else{
			echo"
			<script>
				ww = window.open(window.location, '_self');
				ww.close();
			</script>
			";
		}
		exit;
	}
}