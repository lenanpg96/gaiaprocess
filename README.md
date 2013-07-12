GaiaProcess
===========

Integração do Gaia (WebService) xml  

O Sistema iValue - Gaia, utilizado por alguns dos meus clientes, não da acesso ao seu banco de dados, 
apenas por envio de XML se tem acesso à base do cliente.

Esta aplicação tem por objetivo importar essas informações fornecidas pelo Gaia, ao site das imobiliárias.
A Aplicação importa 1...N clientes cadastrados.

Ela foi estruturada em 3 passos, para evitar assim um estouro de memória devido à grande falta de flags no arquivo
de exportação como, (atualização|inserção|remoção) e à necessidade de download dos XML que contém as informações.

são eles:

1. download e preparação de processamento do XML (xml_controller.php)
2. processamento (processamento_controller.php)
3. download de imagens (image_controller.php)

O primeiro e segundo passo, ficam em um servidor local/dedicado, para questões de performance.
O terceiro passo fica na nuvem no servidor remoto do site. este passo é replicado em todas as aplicações 
que foram processadas.

Todos os passos enviam relátorios com o tempo de execução e pico de uso de acordo com a periodicidade definida
pela necessidade da aplicação.
