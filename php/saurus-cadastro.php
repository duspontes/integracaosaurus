<?php

set_time_limit(0);
ini_set("memory_limit", -1);

require_once("websac/require_file.php");
require_file("def/function.php");
require_file("class/connection.class.php");
require_file("lib/saurus-1.0/nusoap.php");
require_file("class/log.class.php");

$server = new nusoap_server();

$namespaceServer = 'http://saurus.net.br/';
$serviceName = 'serviceCadastros';
$server->configureWSDL($serviceName, $namespaceServer, $endpoint = false, 'document');
$server->decode_utf8 = true;


$server->register('retCadastros', array('xBytesParametros' => 's:base64Binary', 'xSenha' => 's:string'), array('retCadastrosResult' => 's:base64Binary', 'xRetNumero' => 's:int', 'xRetTexto' => 's:string'), $namespaceServer, $namespaceServer.'retCadastroArquivo', 'document', 'literal');

function retCadastros($xBytesParametros, $xSenha){
	$xBytesParametros = base64_decode((string) $xBytesParametros);
	$xBytesParametros = gzdecode((string) $xBytesParametros);

	$xmlParametros = simplexml_load_string($xBytesParametros);

	$xTpSync = (String) $xmlParametros->TpSync;
	$xDUpdate = (String) $xmlParametros->DhReferencia;
	$Dominio = (String) $xmlParametros->Dominio;

	$dataupdade = explode("T", $xDUpdate);

	$con = new Connection();

	$res = $con->query("SELECT codestabelec FROM estabelecimento WHERE  replace(replace(replace(cpfcnpj,'.',''),'/',''),'-','') = '$Dominio'");
	$codestabelec = $res->fetchColumn();


	escreverlog("Parametros: {$xBytesParametros}");
	escreverlog("Codestabelec $codestabelec");

	if(strlen($codestabelec) == 0){
		logsdeerro("CNPJ diferente do websac com o saurus");
		return array('xRetNumero' => 1, 'xRetTexto' => "CNPJ diferente do websac com o saurus");
	}

	if($xTpSync == "2"){
		$query = "SELECT COUNT(produto.codproduto) ";
		$query .= "FROM produtoestab ";
		$query .= "INNER JOIN produto USING (codproduto) ";
		$query .= "WHERE  ((produto.datalog > '{$dataupdade[0]}' OR produtoestab.datalog > '{$dataupdade[0]}') ";
		$query .= "OR (	(produto.datalog = '{$dataupdade[0]}'  AND produto.horalog >= '{$dataupdade[1]}') ";
		$query .= "OR (produtoestab.datalog = '{$dataupdade[0]}' AND  produtoestab.horalog >= '{$dataupdade[1]}') ";
		$query .= ") ";
		$query .= ") ";
		$query .= "AND produto.gerapdv = 'S' AND produtoestab.codestabelec = $codestabelec ";
		$res = $con->query($query);
		$count = $res->fetchColumn();

		if($count == 0){
			$xVerificaStatus = 0;
			$xRettexto = "Consulta realizada com Sucesso";
			return array('xRetNumero' => $xVerificaStatus, 'xRetTexto' => $xRettexto);
		}
	}

	//xTpSync 0-> CARGA COMPLETA 1-> ABERTURA (DATA) 2-> CADA MINUTO (PRECO) -> NULL
	if($xTpSync == "0"){
		$dataupdade[0] = "1800-01-01";
	}
	if(!is_file("tmpsauruscarga.tmp") || $xTpSync == "0"){
		$tmpfname = tempnam("", "tmpsauruscarga.tmp");

		$query = "SELECT saurus_carga('$dataupdade[0]','$dataupdade[1]',$codestabelec) ";
		$res = $con->query($query);
		$str = $res->fetchColumn();

		escreverlog("Carga: {$str}");

		unlink($tmpfname);
		$ret = gzencode($str);
		$in_str = array();
		$in_str = base64_encode($ret);

		$xVerificaStatus = 0;
		$xRettexto = "Consulta realizada com Sucesso";

		if(strlen($str) == 0){
			return array('xRetNumero' => $xVerificaStatus, 'xRetTexto' => $xRettexto);
		}else{
			return array('retCadastrosResult' => $in_str, 'xRetNumero' => $xVerificaStatus, 'xRetTexto' => $xRettexto);
		}
	}
}

function escreverlog($texto){
	$log = new Log("saurus-cadastro");
	if(param("SISTEMA","LOGPDV") == "S"){
		$log->write($texto);
	}
	return true;
}

function logsdeerro($texto){
	$file = fopen("../proc/error.log", "a+");
	fwrite($file, date("d/m/Y H:i:s")." - \r\n".$texto."\r\n\r\n");
	fclose($file);
	return true;
}

$server->service(file_get_contents("php://input"));
exit();
