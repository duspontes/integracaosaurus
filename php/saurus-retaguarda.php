<?php

set_time_limit(0);
ini_set("memory_limit", -1);

require_once("websac/require_file.php");
require_file("def/function.php");
require_file("class/connection.class.php");
require_file("lib/saurus-1.0/nusoap.php");
require_file("class/log.class.php");

$server = new nusoap_server();

$namespaceServer = 'http://saurus.net.br/';   //declaraÃ§Ã£o do NameSpaceServer saurus
$serviceName = 'serviceRetaguarda'; //declaraÃ§ao do nome do serviÃ§o
$server->configureWSDL($serviceName, $namespaceServer, $endpoint = false, 'document');  //configuraÃ§Ã£o Wsdl
$server->decode_utf8 = true;   //configura decode

$server->register('evProcessarFidelidade', array('xBytesParametros' => 's:base64Binary', 'xSenha' => 's:string'), array('evProcessarFidelidadeResult' => 's:base64Binary', 'xRetNumero' => 's:int', 'xRetTexto' => 's:string'), $namespaceServer, $namespaceServer.'evProcessarFidelidade', 'document', 'literal');
$server->register('retProdutoEstoque', array('xBytesParametros' => 's:base64Binary', 'xSenha' => 's:string'), array('retProdutoEstoqueResult' => 's:base64Binary', 'xRetNumero' => 's:int', 'xRetTexto' => 's:string'), $namespaceServer, $namespaceServer.'retProdutoEstoque', 'document', 'literal');
$server->register('retMovimentacoes', array('xBytesParametros' => 's:base64Binary', 'xSenha' => 's:string'), array('retMovimentacoesResult' => 's:base64Binary', 'xRetNumero' => 's:int', 'xRetTexto' => 's:string'), $namespaceServer, $namespaceServer.'retMovimentacoes', 'document', 'literal');
$server->register('evSalvarMovStatus', array('xBytesParametros' => 's:base64Binary', 'xSenha' => 's:string'), array('evSalvarMovStatusResult' => 's:base64Binary', 'xRetNumero' => 's:int', 'xRetTexto' => 's:string'), $namespaceServer, $namespaceServer.'evSalvarMovStatus', 'document', 'literal');
$server->register('retNNfMovimentacaoAtual', array('xBytesParametros' => 's:base64Binary', 'xSenha' => 's:string'), array('retNNfMovimentacaoAtualResult' => 's:base64Binary', 'xRetNumero' => 's:int', 'xRetTexto' => 's:string'), $namespaceServer, $namespaceServer.'retNNfMovimentacaoAtual', 'document', 'literal');

function evProcessarFidelidade($xBytesParametros, $xSenha){
	$xmlParametros = descompactaXml($xBytesParametros);

	escreverlog("Parametros do saurus ".$xmlParametros->asXML());

	// tparq 0 Atendido
	$tparq = (String) $xmlParametros->ArquivoXml->root->tparq;
	$idtef = (String) $xmlParametros->ArquivoXml->root->idtef;

	if($tparq == "0"){
		$numcartao = (String) $xmlParametros->ArquivoXml->root->numcartao;
		$numcartao = removeformat($numcartao);
		$vsolicitado = (String) $xmlParametros->ArquivoXml->root->vsolicitado;
	}else{
		$idusuario = (String) $xmlParametros->ArquivoXml->root->idusuario;
		$motestorno = (String) $xmlParametros->ArquivoXml->root->motestorno;
	}

	escreverlog((string) $xmlParametros->asXML());

	// Estorno de convenio
	if($tparq != "0"){
		$xRettexto = "Cancelado";
		$retProcessarFidelidade = new SimpleXMLElement("<retProcessarFidelidade></retProcessarFidelidade>");
		$root = $retProcessarFidelidade->addChild("root");
		$root->addChild("tparq", 2, null);
		$root->addChild("idtef", $idtef, null);
		$root->addChild("codaut", "228", null);
		$root->addChild("msgoperador", $motestorno, null);

		$dataatual = date("d/m/Y");

		$strImpressao = "<n>Estorno de credito</n>\r\n";
		$strImpressao .= "\r\n";
		$strImpressao .= "<esquerda><n>Motivo:</n>$motestorno\r\n";
		$strImpressao .= "\r\n";
		$strImpressao .= "\r\n";
		$strImpressao .= "\r\n";

//		$retProcessarFidelidade->addChild("retImpressao", $strImpressao, null);
		$ret = $retProcessarFidelidade->asXML();

		return array("evProcessarFidelidadeResult" => compactaXml($ret), 'xRetNumero' => "0", 'xRetTexto' => $xRettexto);

		// Finalizar convenio
	}else{
		$con = new Connection();
		$query = "SELECT coalesce(limite1-debito1,0) AS saldo, nome, cpfcnpj, ";
		$query .= "codcliente, limite1, debito1 ";
		$query .= "FROM cliente ";
		$query .= "WHERE regexp_replace(cpfcnpj , '[^0-9]*', '', 'g') = '$numcartao' OR codcliente = $numcartao ";
		$res = $con->query($query);
		$row = $res->fetch();

		$saldo = $row["saldo"];


		if($saldo > $vsolicitado){
			$nomecliente = $row["nome"];
			$cpfcnpj = removeformat($row["cpfcnpj"]);

			$retProcessarFidelidade = new SimpleXMLElement("<retProcessarFidelidade></retProcessarFidelidade>");
			$root = $retProcessarFidelidade->addChild("root");
			$root->addChild("tparq", 2, null);
			$root->addChild("idtef", $idtef, null);
			$root->addChild("codaut", "228", null);
			$root->addChild("idfaturapag", null, null);
			$root->addChild("numcartao", $cpfcnpj, null);
			$root->addChild("nomecartao", $nomecartao, null);
			$root->addChild("vsolicitado", $vsolicitado, null);
			$root->addChild("qparc", "1", null);
			$root->addChild("odometro", "0.00", null);
			$root->addChild("infadic", null, null);
			$root->addChild("indstatus", "0", null);
			$root->addChild("idestorno", "0", null);
			$root->addChild("motestorno", null, null);
			$root->addChild("destorno", "1900-01-01T00:00:00", null);
			$root->addChild("idusuario", null, null);
			$root->addChild("dsolicitacao", date("Y-m-d")."T".date("H:m:i"), null);
			$root->addChild("idCliente", null, null);
			$root->addChild("docCliente", $cpfcnpj, null);
			$root->addChild("nomeCliente", $nomecliente, null);
			$root->addChild("utilizaPosto", null, null);
			$root->addChild("msgoperador", "Transacao Aprovada", null);

			$dataatual = date("d/m/Y");

			$limite = number_format($row["limite1"], 2, ",", "");
			$debito = number_format($row["debito1"] + $vsolicitado, 2, ",", "");
			$sldatual = number_format($row["limite1"] - $row["debito1"] - $vsolicitado, 2, ",", "");

			$strImpressao = "<n>Saldo</n>\r\n";
			$strImpressao .= "\r\n";
			$strImpressao .= "<esquerda><n>Data:</n> $dataatual\r\n";
			$strImpressao .= "<esquerda><n>Cliente:</n> $nomecliente \r\n";
			$strImpressao .= "<esquerda><n>Codigo cliente:</n> {$row["codcliente"]}\r\n";
			$strImpressao .= "<esquerda><n>CPF:</n> $cpfcnpj \r\n";
			$strImpressao .= "<esquerda><n>Limite:</n> $limite\r\n";
			$strImpressao .= "<esquerda><n>Debito:</n> $debito\r\n";
			$strImpressao .= "<esquerda><n>Saldo atual:</n> $sldatual\r\n";
			$strImpressao .= "\r\n";
			$strImpressao .= "\r\n";
			$strImpressao .= "\r\n";

			$strImpressao .= "<guilhotina>";
			$vsolicitado = number_format($vsolicitado, 2, ",", "");
			$strImpressao .= "<n><centro>Convênio</n>\r\n";
			$strImpressao .= "\r\n";
			$strImpressao .= "<esquerda><n>Data:</n> $dataatual\r\n";
			$strImpressao .= "<esquerda><n>Cliente:</n> $nomecliente \r\n";
			$strImpressao .= "<esquerda><n>Codigo cliente:</n> {$row["codcliente"]}\r\n";
			$strImpressao .= "<esquerda><n>CPF:</n> $cpfcnpj \r\n";
			$strImpressao .= "<esquerda><n>Valor:</n> $vsolicitado\r\n";

			$strImpressao .= "\r\n";
			$strImpressao .= "\r\n";
			$strImpressao .= "<centro>_____________________________\r\n";
			$strImpressao .= "<centro>Assinatura\r\n";
			$strImpressao .= "\r\n";
			$strImpressao .= "\r\n";
			$strImpressao .= "\r\n";

			$retProcessarFidelidade->addChild("retImpressao", $strImpressao, null);

			$xRettexto = "Consulta realizada com Sucesso";

			$ret = $retProcessarFidelidade->asXML();

			escreverlog("retProcessarFidelidade ".$retProcessarFidelidade->asXML());
			return array("evProcessarFidelidadeResult" => compactaXml($ret), 'xRetNumero' => "0", 'xRetTexto' => $xRettexto);
		}else{
			$xRettexto = "Cliente se encontra sem saldo";
			return array('xRetNumero' => "1", 'xRetTexto' => $xRettexto);
		}
	}
}

function retProdutoEstoque($xBytesParametros, $xSenha){
	$xmlParametros = descompactaXml($xBytesParametros);

	escreverlog("Parametros retProdutoEstoque ".$xmlParametros->asXML());

	$codproduto = $xmlParametros->IdProduto;
	$codestabelec = $xmlParametros->IdLoja;

	if(strlen($codproduto) == 0){
		$codproduto = $xmlParametros->CodProduto;
	}

	$query = "SELECT codestabelec, sldatual FROM produtoestab WHERE codproduto = $codproduto  ";
	if(strlen($codestabelec) > 0){
		$query .= " AND codestabelec = $codestabelec ";
	}

	$con = new Connection();
	$res = $con->query($query);
	$arr = $res->fetchAll();

	$ret = "<retProdutoEstoque>";
	foreach($arr as $row){
		$ret .= "<EstoqueLoja idLoja=\"{$row["codestabelec"]}\" fant=\"LJ {$row["codestabelec"]} \" qSaldo=\"{$row["sldatual"]}\" />";
	}

	$ret .= "</retProdutoEstoque>";
	$xRettexto = "Consulta realizada com sucesso";
	escreverlog("Retorno estoque ".$ret);
	return array("retProdutoEstoqueResult" => compactaXml($ret), 'xRetNumero' => "0", 'xRetTexto' => $xRettexto);
}

function evSalvarMovStatus($xBytesParametros, $xSenha){
	$xmlParametros = descompactaXml($xBytesParametros);
	escreverlog("Parametros retProdutoEstoque ".$xmlParametros->asXML());

	$TpStatus = (string) $xmlParametros->TpStatus;

	if($TpStatus == "3511"){
		$xRettexto = "Consulta realizada com Sucesso";

		$ret = guidv4();
		$ret = "<retSalvarMovStatus><mov_idStatus>$ret</mov_idStatus></retSalvarMovStatus>";
		escreverlog($ret);
		return array("evSalvarMovStatusResult" => compactaXml($ret), 'xRetNumero' => "0", 'xRetTexto' => $xRettexto);
	}else{
		$xRettexto = "Houve erros consulte o log para mais detalhes";
		return array('xRetNumero' => "1", 'xRetTexto' => $xRettexto);
	}
}

function retNNfMovimentacaoAtual($xBytesParametros, $xSenha){
	$xmlParametros = descompactaXml($xBytesParametros);
	escreverlog("Parametros retNNfMovimentacaoAtual ".$xmlParametros->asXML());

	$NumCaixa = (string) $xmlParametros->NumCaixa;
	$IdLoja = (string) $xmlParametros->IdLoja;
	$TpMov = (string) $xmlParametros->TpMov;

	if($TpMov == "40"){
		$query = "SELECT COALESCE(MAX(cupom),0) ";
		$query = "FROM cupom ";
		$query .= "WHERE cupom.codestabelec = $IdLoja AND cupom.caixa = '$NumCaixa' AND cupom.codecf is null ";

		$res = $con->query($query);
		escreverlog($query);
		$cupom = $res->fetchColumn();

		$ret = "<retConsulta><ultNNF>$cupom</ultNNF></retConsulta>";
		escreverlog($ret);
		return array("retNNfMovimentacaoAtualResult" => compactaXml($ret), 'xRetNumero' => "0", 'xRetTexto' => $xRettexto);
	}else{
		$xRettexto = "Nada encontrado";
		return array('xRetNumero' => "1", 'xRetTexto' => $xRettexto);
	}
}

function retMovimentacoes($xBytesParametros, $xSenha){
	$con = new Connection();

	$xmlParametros = descompactaXml($xBytesParametros);
	escreverlog("Parametros retProdutoEstoque ".$xmlParametros->asXML());

	$codorcamento = (string) $xmlParametros->NNF;
	$cpf = (string) $xmlParametros->Doc;
	$cpf = removeformat(trim($cpf));
	$nome = (string) $xmlParametros->xNome;

	$query = "SELECT orcamento.codorcamento ";
	$query .= "FROM orcamento ";
	$query .= "INNER JOIN cliente ON (orcamento.codcliente = cliente.codcliente) ";
	$query .= "WHERE orcamento.codfunc is not null AND orcamento.status = 'P' ";
	if(strlen($cpf) > 0){
		$query .= " AND regexp_replace(cliente.cpfcnpj,'[^0-9]','','g')  = '$cpf' ";
	}elseif(strlen($nome) > 0){
		$query .= " AND cliente.nome ilike '%$nome%' ";
	}elseif(strlen($codorcamento) > 0){
		$query .= " AND orcamento.codorcamento = $codorcamento ";
	}
	$query .= " ORDER BY orcamento.codorcamento desc limit 10 ";

	$res = $con->query($query);
	$arr_codorcamento = $res->fetchAll(PDO::FETCH_COLUMN, 0);

	$retConsutaMov = new SimpleXMLElement("<retConsutaMov></retConsutaMov>");
	foreach($arr_codorcamento AS $codorcamento){
		$query = "SELECT orcamento.codorcamento, orcamento.dtemissao, orcamento.hremissao, orcamento.codestabelec, ";
		$query .= "orcamento.codcliente, cliente.nome, cliente.cpfcnpj, orcamento.totalliquido, ";
		$query .= "itorcamento.qtdeunidade, itorcamento.quantidade, itorcamento.valdescto, ";
		$query .= "unidade.sigla, produto.codproduto, produto.descricaofiscal, itorcamento.totalliquido AS ittotalliquido, ";
		$query .= "estabelecimento.cpfcnpj AS estab_cnpj, orcamento.codfunc, itorcamento.preco ";
		$query .= "FROM orcamento ";
		$query .= "INNER JOIN itorcamento ON (orcamento.codorcamento = itorcamento.codorcamento) ";
		$query .= "INNER JOIN produto ON (itorcamento.codproduto = produto.codproduto) ";
		$query .= "INNER JOIN embalagem ON (produto.codembalvda = embalagem.codembal) ";
		$query .= "INNER JOIN unidade ON (embalagem.codunidade = unidade.codunidade) ";
		$query .= "INNER JOIN estabelecimento ON (orcamento.codestabelec = estabelecimento.codestabelec) ";
		$query .= "INNER JOIN cliente ON (orcamento.codcliente = cliente.codcliente) ";
		$query .= "WHERE orcamento.codorcamento = $codorcamento ";

		$res = $con->query($query);
		escreverlog($query);
		$arr_orcamento = $res->fetchAll(2);

		escreverlog("quantidade itens: ".sizeof($arr_orcamento));

		if(sizeof($arr_orcamento) == 0){
			$xRettexto = "Pedido nao encontrado";
			return array('xRetNumero' => "1", 'xRetTexto' => $xRettexto);
		}

		foreach($arr_orcamento AS $itorcamento){
			$totalquantidade += $itorcamento["quantidade"] * $itorcamento["qtdeunidade"];
		}

		$orcamento = reset($arr_orcamento);

		$dhEmissao = $orcamento["dtemissao"]."T".substr($orcamento["hremissao"], 0, 8);

		$MovDados = $retConsutaMov->addChild("MovDados");

		//	XXXXXXXX-XXXX-XX00-0000-NNNNNNNNNNNN guididmov X=cnpj N=xcodorcamento
		$estab_cnpj = removeformat($orcamento["estab_cnpj"]);

		if(strlen($orcamento["cpfcnpj"]) <= 0){
			$codcliente = 1;
		}else{
			$codcliente = $orcamento["codcliente"];
		}

		$guidIdMov = substr($estab_cnpj, 0, 8)."-".substr($estab_cnpj, 8, 4)."-".substr($estab_cnpj, -2)."00-0000-".str_pad($codorcamento, 12, "0", STR_PAD_LEFT);

		$MovDados->addAttribute("mov_idMov", $guidIdMov);
		$MovDados->addAttribute("mov_tpMov", "40");
		$MovDados->addAttribute("mov_dhEmi", $dhEmissao);
		$MovDados->addAttribute("emit_idLoja", $orcamento["codestabelec"]);
		$MovDados->addAttribute("mov_nNf", $orcamento["codorcamento"]);
		$MovDados->addAttribute("mov_idOperador", $orcamento["codfunc"] + 100000);
		$MovDados->addAttribute("dest_idCadastro", $codcliente);
		$MovDados->addAttribute("dest_doc", removeformat($orcamento["cpfcnpj"]));
		$MovDados->addAttribute("dest_xNome", $orcamento["nome"]);
		$MovDados->addAttribute("mov_indStatus", "0");
		$MovDados->addAttribute("tot_qCom", $totalquantidade);
		$MovDados->addAttribute("tot_qtdItens", sizeof($arr_orcamento));
		$MovDados->addAttribute("tot_vNF", $orcamento["totalliquido"]);

		$Produtos = $MovDados->addChild("Produtos");

		foreach($arr_orcamento AS $itorcamento){
			$seqitem++;
			$MovProd = $Produtos->addChild("MovProd");
			$MovProd->addAttribute("prod_nItem", $seqitem);
			$MovProd->addAttribute("prod_idVendedor", $orcamento["codfunc"] + 100000);
			$MovProd->addAttribute("prod_cProd", $itorcamento["codproduto"]);
			$MovProd->addAttribute("prod_xProd", $itorcamento["descricaofiscal"]);
			$MovProd->addAttribute("prod_vUnCom", $itorcamento["preco"]);
			$MovProd->addAttribute("prod_qCom", $itorcamento["quantidade"]);
			$MovProd->addAttribute("prod_uCom", $itorcamento["sigla"]);
			$MovProd->addAttribute("prod_vDesc", $itorcamento["valdescto"]);
			$MovProd->addAttribute("prod_vOutro", "0");
			$MovProd->addAttribute("prod_vProd", $itorcamento["ittotalliquido"]);
			$MovProd->addAttribute("prod_infAdProd", "2");
			$MovProd->addAttribute("prod_idVendedor", "");
			$MovProd->addAttribute("prod_loginVendedor", "");
		}
	}

	$xRettexto = "Consulta realizada com Sucesso";

	$ret = $retConsutaMov->asXML();
	escreverlog($ret);
	return array("retMovimentacoesResult" => compactaXml($ret), 'xRetNumero' => "0", 'xRetTexto' => $xRettexto);
}

function descompactaXml($xml){
	$xml = base64_decode((string) $xml);
	$xml = gzdecode((string) $xml);

	return simplexml_load_string($xml);
}

function compactaXml($xml){
	$xml = gzencode($xml);
	$xml = base64_encode($xml);

	return $xml;
}

function escreverlog($texto){
	$log = new Log("saurus-retaguarda");
	if(param("SISTEMA", "LOGPDV") == "S"){
		$log->write($texto);
	}
	return true;
}

function guidv4(){
	if(function_exists('com_create_guid') === true)
		return trim(com_create_guid(), '{}');

	$data = openssl_random_pseudo_bytes(16);
	$data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
	$data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
	return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

$server->service(file_get_contents("php://input"));
exit();
