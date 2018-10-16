<?php
require_once("websac/require_file.php");
require_file("def/function.php");
require_file("class/connection.class.php");
require_file("lib/saurus-1.0/nusoap.php");
require_file("class/log.class.php");
require_file("class/saurus.class.php");

$server = new nusoap_server();

$namespaceServer = 'http://saurus.net.br/';   //declaraÃ§Ã£o do NameSpaceServer saurus
$serviceName = 'serviceRecepcao'; //declaraÃ§ao do nome do serviÃ§o
$server->configureWSDL($serviceName, $namespaceServer, $endpoint = false, 'document');  //configuração Wsdl
$server->decode_utf8 = true; //configura decode
$server->register('envArqIntegracao', array('xBytesParametros' => 's:base64Binary', 'xSenha' => 's:string'), array('envArqIntegracaoResult' => 's:string', 'xRetTexto' => 's:string', 'xRetNumero' => 's:int'), $namespaceServer, $namespaceServer.'envArqIntegracao', 'document', 'literal');

function envArqIntegracao($xBytesParametros, $xSenha){
	$xBytesParametros = base64_decode((string) $xBytesParametros);
	$xBytesParametros = gzdecode((string) $xBytesParametros);

	$xmlParametros = simplexml_load_string($xBytesParametros);

	$xDominio = (String) $xmlParametros->Dominio;
	$xTpLanc = (String) $xmlParametros->TpLanc;
	$xTpArqXml = (String) $xmlParametros->TpArqXml;
	$xId = (String) $xmlParametros->IdReg;
	$xNumCaixa = (String) $xmlParametros->NumCaixa;

	$con = new Connection();

	$res = $con->query("SELECT codestabelec FROM estabelecimento WHERE  replace(replace(replace(cpfcnpj,'.',''),'/',''),'-','') = '$xDominio'");
	$codestabelec = $res->fetchColumn();

	if(removeformat($xDominio) == "53485215000106"){
		$codestabelec = "1";
	}

	if($xTpArqXml == "20" && $xTpLanc == "0"){
		$xArqXml = (string) $xmlParametros->ArquivoXml->children()->asXML();

		$saurus = new Saurus($con);
		$xArqXml = str_replace(array("'","\r", "\n"), "", $xArqXml);

		if(!$saurus->processar_venda($xArqXml, $codestabelec, $xId)){
			escreverlog("Deu erro *** xArqXml: $xArqXml  codestabelec: $codestabelec xId: $xId");

			$saurus->erro("Error: {$_SESSION["ERROR"]} XML: $xArqXml");
			return array('xRetTexto' => "Erro", 'xRetNumero' => 1);
		}else{
			$res = $con->query("SELECT count(arqxml) FROM saurusvenda WHERE  arqxml = '$xArqXml'");
			$contSaurusVenda = $res->fetchColumn();

			if($contSaurusVenda == 0){
				$query = "INSERT INTO saurusvenda(codestabelec,arqxml,referencia,caixa) VALUES ($codestabelec,'$xArqXml','$xId',$xNumCaixa) ";
			}

			escreverlog("Deu certo *** $query ");
			$con->exec($query);
		}
	}elseif($xTpArqXml == "1" && $xTpLanc == "1"){
		$xArqXml = (string) $xmlParametros->ArquivoXml->children()->asXML();

		$saurus = new Saurus($con);
		$xArqXml = str_replace(array("\r", "\n"), "", $xArqXml);

		if(!$saurus->mov_status($xArqXml, $codestabelec, $xNumCaixa)){
			escreverlog("Deu erro *** xArqXml: $xArqXml  codestabelec: $codestabelec xId: $xId");

			$saurus->erro("Error: {$_SESSION["ERROR"]} XML: $xArqXml");
			return array('xRetTexto' => "Erro", 'xRetNumero' => 1);
		}else{
			escreverlog("Deu certo *** movimentação ");
		}
	}

	$xRetTexto =  "Arquivo processado com sucesso";
	$retxml = "<retRecebimento><idRecepcao>".guidv4()."</idRecepcao></retRecebimento>";
	escreverlog("GUID ***$retxml*** xArqXml: $xArqXml  codestabelec: $codestabelec xId: $xId cnpj: $xDominio");
	return array('envArqIntegracaoResult' => compactaXml($retxml), 'xRetTexto' => $xRetTexto, 'xRetNumero' => 0);
}

function guidv4(){
	if(function_exists('com_create_guid') === true){
			return trim(com_create_guid(), '{}');
	}

	$data = openssl_random_pseudo_bytes(16);
	$data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
	$data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
	return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function escreverlog($texto){
	$log = new Log("saurus-cupom");
	if(param("SISTEMA","LOGPDV") == "S"){
		$log->write($texto);
	}
	return true;
}

function descompactaXml($xml) {
    $xml = base64_decode((string) $xml);
    $xml = gzdecode((string) $xml);

    return simplexml_load_string($xml);
}

function compactaXml($xml) {
    $xml = gzencode($xml);
    $xml = base64_encode($xml);

    return $xml;
}

$server->service(file_get_contents("php://input"));
exit();
