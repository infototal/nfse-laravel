<?php

// add to protected/config/main.php
/*return array(
	...
	'import'=>array(
		...
		'application.extensions.nfse.*',
		...
	),
	'components'=>array(
		...
		'nfse'=>array(
			'class'=>'NFSe',
			'useSandbox'=>true, // true p/ homologação 
			'xmlValidation'=>true,
			'defaultCertificate'=>'<CERTIFICATE_PEM_PATH>',
			'defaultPrivateKey'=>'<PRIVATE_KEY_PEM_PATH>',
			'defaultPassword'=>'<PASSWORD>',
			'defaultCnpj'=>'<CNPJ>',
			'defaultInscricaoMunicipal'=>'<INSCRICAOMUNICIPAL>',
		),
		...
	),
);*/

// prepare RPS with all NFSe information
/*function prepareRPS($number, $user, $description, $amount, $when=null)
{
	if($when===null)
		$when=time();

	$address=$user->enderecoCobranca;
	if($user->pessoaJuridica!==null)
	{
		$business=$user->pessoaJuridica;
		$taxKey='Cnpj';
		$taxId=$business->cnpj;
		$name=$business->razao_social;
		if($address===null) $address=$user->enderecoComercial;
	}
	else
	if($user->pessoaFisica!==null)
	{
		$personal=$user->pessoaFisica;
		$taxKey='Cpf';
		$taxId=$personal->cpf;
		$name=$personal->nome_completo;
		if($address===null) $address=$user->enderecoResidencial;
	}
	else
	{
		return null;
	}
	if($address===null) $address=$user->enderecoFisico;
	if($address===null) return null;
	$phone=$user->bestPhone;
	$email=$user->email;

	// Identificação
	$rps['IdentificacaoRps']['Numero'] = date('Ym',$when).sprintf('%09d',$number);
	$rps['IdentificacaoRps']['Serie'] = $params['devMode']?'D0001':'00001';
	// 1 - RPS
	// 2 - Nota Fiscal Conjugada (Mista)
	// 3 - Cupom
	$rps['IdentificacaoRps']['Tipo'] = 1;

	// Data de Emissão
	$rps['DataEmissao'] = date('Y-m-d\TH:i:s',$when);

	// 1 - Tributação no município
	// 2 - Tributação fora do município
	// 3 - Isenção
	// 4 - Imune
	// 5 - Exigibilidade suspensa por decisão judicial
	// 6 - Exigibilidade suspensa por procedimento administrativo
	$rps['NaturezaOperacao'] = 1;

	// 1 - Microempresa municipal
	// 2 - Estimativa
	// 3 - Sociedade de profissionais
	// 4 - Cooperativa
	// 5 - MEI - Simples Nacional
	// 6 - ME EPP - Simples Nacional
	$rps['RegimeEspecialTributacao'] = 6;

	// 1 - Optante Simples Nacional
	// 2 - Nao-Optante Simples Nacional
	$rps['OptanteSimplesNacional'] = 1;

	// 1 - Incentivador cultural
	// 2 - Nao-incentivador cultural
	$rps['IncentivadorCultural'] = 2;

	// Status
	// 1 - Normal
	// 2 - Cancelado
	$rps['Status'] = 1;

	// Servico
	$rps['Servico']['Valores']['ValorServicos'] = $amount;

	// 1 - ISS retido
	// 2 - ISS nao-retido
	$rps['Servico']['Valores']['IssRetido'] = 2;
	$rps['Servico']['Valores']['BaseCalculo'] = $amount;
	$rps['Servico']['Valores']['ValorLiquidoNfse'] = $amount;
	$rps['Servico']['ItemListaServico'] = '9.02'; // classificacao do servico
	$rps['Servico']['CodigoTributacaoMunicipio'] = '090200188'; // codigo do servico
	$rps['Servico']['Discriminacao'] = $description;
	$rps['Servico']['CodigoMunicipio'] = '3106200'; // BH

	// Prestador
	$rps['Prestador']['Cnpj']=$params['cnpj'];
	$rps['Prestador']['InscricaoMunicipal']=$params['inscricaoMunicipal'];

	// Tomador
	$rps['Tomador']['IdentificacaoTomador']['CpfCnpj'][$taxKey]=$taxId;
	$rps['Tomador']['RazaoSocial']=$name;
	$rps['Tomador']['Endereco']['Endereco']=$address->logradouro;
	$rps['Tomador']['Endereco']['Numero']=empty($address->numero)?'S/N':$address->numero;
	if(!empty($address->complemento)) $rps['Tomador']['Endereco']['Complemento']=$address->complemento;
	$rps['Tomador']['Endereco']['Bairro']=empty($address->bairro)?'S/B':$address->bairro;
	$rps['Tomador']['Endereco']['CodigoMunicipio']=$address->codigoLocalidade; // codigo da localidade do IBGE
	$rps['Tomador']['Endereco']['Uf']=$address->uf; // SP MG etc
	$rps['Tomador']['Endereco']['Cep']=$address->cep; // only the 8 digits
	if($phone!==null) $rps['Tomador']['Contato']['Telefone']=$phone->numero; // only the 10 or 11 digits
	$rps['Tomador']['Contato']['Email']=$email;

	return $rps;
}

// call the API to produce the NFSe from the RPS
/*function processNfse($rps)
{
	$params = $app->params;
	$nfse = $app->nfse;

	$result=$nfse->api->GerarNfse(array(
		'LoteRps'=>array(
			'NumeroLote'=>1,
			'Cnpj'=>$params['cnpj'],
			'InscricaoMunicipal'=>$params['inscricaoMunicipal'],
			'QuantidadeRps'=>1,
			'ListaRps'=>array(
				'Rps'=>array(
					'InfRps'=>$rps,
				),
			),
		),
	));

	if($result===false)
		return false;

	if(!isset($result->ListaNfse))
	{
		Yii::log('Failure processing NFS-e: '.print_r($rps,true).print_r($result,true).$nfse->api->inputXml,CLogger::LEVEL_ERROR,'app.billing.nfse.process');

		return false;
	}

	return array($nfse->api->inputXml,$nfse->api->outputXml);
}
*/

// generate the PDF from the output returned by the API
/*function generatePdf($outputXml)
{
	$app=Yii::app();
	$nfse=$app->nfse;

	// extract nfse from output
	$dom=@DOMDocument::loadXML($xml);
	if($dom===false) return false;
	$nodes=$dom->getElementsByTagName('CompNfse');
	if($nodes->length!==1) return false;
	$compNfse=$nodes->item(0);
	$xml=$dom->saveXML($compNfse);

	$dom=new DOMDocument('1.0','UTF-8');
	$dom->loadXML($xml);
	$xml=$dom->saveXML();

	return $nfse->producePdf($xml,function($code)
	{
		// must map IBGE code to city name
		Yii::log('Código de cidade do IBGE desconhecido: '.$code,CLogger::LEVEL_ERROR,'app.export.nfse.pdf');
		return '';
	},function($code)
	{
		// must map class to description
		switch($code)
		{
		case '9.02': return 'Agenciamento, organizacao, promocao, intermediacao e execucao de programas de turismo, passeios, viagens, excursoes, hospedagens e congeneres.'; break;
		}
		Yii::log('Código do item da lista de serviços desconhecido: '.$code,CLogger::LEVEL_ERROR,'app.export.nfse.pdf');
		return '';
	},function($code)
	{
		// must map code to description
		switch($code)
		{
		case '090200188': return 'Agenciamento, intermediação e promoção de pacotes e programas turísticos, passeios, viagens, excursões, hospedagens, reservas e congêneres.'; break;
		}
		Yii::log('Código de tributação do município desconhecido: '.$code,CLogger::LEVEL_ERROR,'app.export.nfse.pdf');
		return '';
	});
}


\PedroSantiago\Nfse\NFSe::get([

]);




// sample uses of the API
$response=Yii::app()->nfse->api->ConsultarNfse(array(
	'Prestador'=>array(
		'Cnpj'=>'99999999000191',
		'InscricaoMunicipal'=>'09999990016',
	),
	'PeriodoEmissao'=>array(
		'DataInicial'=>'2011-02-16',
		'DataFinal'=>'2011-04-06',
	),
	'Tomador'=>array(
		'CpfCnpj'=>array(
			'Cnpj'=>'99999999000191',
		),
	),
));
print_r($response);
**/

$api = new \PedroSantiago\Nfse\Api([
	'class'=>'NFSe',
	'env' => 'staging', // true p/ homologação
	'xmlValidation' => true,
	'certificate' => 'CERTIFICATE_PEM_PATH',
	'privateKey' => 'PRIVATE_KEY_PEM_PATH',
	'password' => '',
	'cnpj' => '',
	'inscricaoMunicipal'=>'',
]);

$response= $api->GerarNfse(array(
	'LoteRps'=>array(
		'NumeroLote'=>1,
		'Cnpj'=>'99999999000191',
		'InscricaoMunicipal'=>'09999990016',
		'QuantidadeRps'=>1,
		'ListaRps'=>array(
			'Rps'=>array(
				'InfRps'=>array(
					'IdentificacaoRps'=>array(
						'Numero'=>5,
						'Serie'=>'AAAAA',
						'Tipo'=>1,
					),
					'DataEmissao'=>'2013-11-23T12:00:00',
					'NaturezaOperacao'=>1,
					'RegimeEspecialTributacao'=>6,
					'OptanteSimplesNacional'=>1,
					'IncentivadorCultural'=>2,
					'Status'=>1,
					'Servico'=>array(
						'Valores'=>array(
							'ValorServicos'=>'200.00',
							'IssRetido'=>2,
							'BaseCalculo'=>'200.00',
							'ValorLiquidoNfse'=>'200.00',
						),
						'ItemListaServico'=>'9.02',
						'CodigoTributacaoMunicipio'=>'791120000',
						'Discriminacao'=>'Reserva de pousadas',
						'CodigoMunicipio'=>'3106200',
					),
					'Prestador'=>array(
						'Cnpj'=>'99999999000191',
						'InscricaoMunicipal'=>'09999990016',
					),
					'Tomador'=>array(
						'IdentificacaoTomador'=>array(
							'CpfCnpj'=>array(
								'Cnpj'=>'99999999000191',
							),
						),
						'RazaoSocial'=>'Teste Ltda',
						'Endereco'=>array(
							'Endereco'=>'Avenida do Contorno',
							'Numero'=>'1433',
							'Bairro'=>'Centro',
							'CodigoMunicipio'=>'3106200',
							'Uf'=>'MG',
							'Cep'=>'30110170',
						),
					),
				),
			),
		),
	),
));
print_r($response);

/*
$response=Yii::app()->nfse->api->CancelarNfse(array(
	'Pedido'=>array(
		'InfPedidoCancelamento'=>array(
			'IdentificacaoNfse'=>array(
				'Numero'=>'201300000000001',
				'Cnpj'=>'99999999000191',
				'InscricaoMunicipal'=>'09999990016',
				'CodigoMunicipio'=>'3106200',
			),
			'CodigoCancelamento'=>'2',
		),
	),
));
print_r($response);

