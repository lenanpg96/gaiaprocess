<?php 
class XmlController extends AppController{
	
	var $uses 		= array();
	var $components = array();
	var $helpers 	= array();
	
	function index(){
		list($usec, $sec) = explode(' ', microtime());
		$script_start = (float) $sec + (float) $usec;
		
		$clientes = ClassRegistry::init('Cliente')->findAll();
		foreach($clientes as $c){
			$this->download_xml($c['Cliente']['xml_url'], $c['Cliente']['slug_cliente']);
			$this->checkLastNew($c['Cliente']['slug_cliente']);
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
		 'host' 	=> "mail.df8.com.br",
		 'auth' 	=> true,
		 'username' => "no-reply@df8.com.br",
		 'password' => "noreply.123",
		 'port'		=> 587
		));
		$headers = array (
		 'From' 	=> "no-reply@df8.com.br",
		 'To' 		=> MAIL_LOG,
		 'Subject' 	=> "Int.Gaia - XML ".date("d/m/y")
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
	
	
	/*
	 * Baixa o XML do cliente.
	 * */
	function download_xml($url, $cliente){
		
		$ch = curl_init($url);
		$fp = @fopen(PATH_TO_FILES.$cliente.DS.'xml'.DS.'new.xml', "w");
		
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		
		curl_exec($ch);
		curl_close($ch);
		fclose($fp);
	}
	
	/*
	 * Verifica se o novo XML é igual ao antigo, e o prepara para processamento
	 * */
	function checkLastNew($cliente){
		
		$path = PATH_TO_FILES.$cliente.DS.'xml'.DS;
		
		if(file_exists($path.'last.xml')){
			
			$sizeL = filesize($path.'last.xml');
			$sizeN = filesize($path.'new.xml');
			
			if($sizeL != $sizeN){
				copy($path.'new.xml', $path.'toProcess'.DS.date('H-i_d-m-Y').'.xml');	
				unlink($path.'last.xml');
				rename($path.'new.xml', $path.'last.xml');
			}else{
				unlink($path.'new.xml');
			}
		}else{
			copy($path.'new.xml', $path.'toProcess'.DS.date('H-i_d-m-Y').'.xml');	
			rename($path.'new.xml', $path.'last.xml');
		}
	}
	
}?>