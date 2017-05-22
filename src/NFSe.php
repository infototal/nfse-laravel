<?php
namespace PedroSantiago\Nfse;

class NFSe
{
	public $useProxy=false;
	public $proxyHost;
	public $proxyPort;
	public $trustAllConnection=false;
	public $useSandbox=false;
	public $xmlValidation=false;

	public $defaultVersion='1.00';
	public $defaultCertificate='';
	public $defaultPrivateKey='';
	public $defaultPassword='';
	public $defaultCnpj='99999999000191';
	public $defaultInscricaoMunicipal='09999990016';

	private $_api;

	public function init()
	{
		parent::init();
		if(empty($defaultCertificate)) $defaultCertificate=dirname(__FILE__).DIRECTORY_SEPARATOR.'certs'.DIRECTORY_SEPARATOR.'certificate.pem';
		if(empty($defaultPrivateKey)) $defaultPrivateKey=dirname(__FILE__).DIRECTORY_SEPARATOR.'certs'.DIRECTORY_SEPARATOR.'private_key.pem';
		$this->_api=new NFSeAPI($this);
	}

	public function getAPI()
	{
		return $this->_api;
	}

	public function getVisualizationUrl()
	{
		$url='https://bhissdigital.pbh.gov.br/nfse/pages/consultaNFS-e_cidadao.jsf';
		if($this->useSandbox) $url=str_replace('bhissdigital.pbh.gov.br','bhisshomologa.pbh.gov.br',$url);
		return $url;
	}

	public function producePdf($inputs,$l1=null,$l2=null,$l3=null)
	{
		if(!is_array($inputs)) $inputs=array($inputs);
		if($l1===null) $l1=function($v) { return ''; };
		if($l2===null) $l2=function($v) { return ''; };
		if($l3===null) $l3=function($v) { return ''; };

		$pdf=new PDF;
		$pdf->SetTitle('NFS-e - Nota Fiscal de Serviços eletrônica');
		foreach($inputs as $input)
		{
			$compNfse=$this->api->parseXml($input);
			if($compNfse===false) return false;

			$this->addNfse2Pdf($pdf,$compNfse,$l1,$l2,$l3);
		}
		return $pdf->Output('','S');
	}

	protected function addNfse2Pdf($pdf,$compNfse,$l1,$l2,$l3)
	{
		$app=Yii::app();
		$dateFormatter=$app->dateFormatter;
		$numberFormatter=$app->numberFormatter;

		$InfNfse=$compNfse->Nfse->InfNfse;
		$Servico=$InfNfse->Servico;
		$Valores=$Servico->Valores;
		$PrestadorServico=$InfNfse->PrestadorServico;
		$TomadorServico=$InfNfse->TomadorServico;

		// fix for 8 or 9 digits
		$Servico->CodigoTributacaoMunicipio=str_pad($Servico->CodigoTributacaoMunicipio,9,'0',STR_PAD_LEFT);

		// A4 = 735 x 1039
		$f=3.5;

		$pdf->AddPage();

		// cinza
		$pdf->SetFillColor(242,242,242);
		$pdf->Rect(42/$f,42/$f,652/$f,26/$f,'F');
		$pdf->Rect(47/$f,341/$f,642/$f,43/$f,'F');
		$pdf->Rect(47/$f,429/$f,642/$f,30/$f,'F');

		// azul claro
		$pdf->SetDrawColor(190,220,226);
		$pdf->Rect(42/$f,42/$f,652/$f,694/$f);
		$pdf->RoundRect(47/$f,186/$f,642/$f,106/$f,3);
		$pdf->RoundRect(47/$f,298/$f,642/$f,38/$f,3);
		$pdf->RoundRect(47/$f,494/$f,309/$f,118/$f,3);
		$pdf->RoundRect(370/$f,494/$f,309/$f,128/$f,3);
		$pdf->Line(42/$f,106/$f,694/$f,106/$f);
		$pdf->Line(42/$f,625/$f,694/$f,625/$f);
		$pdf->Line(42/$f,650/$f,694/$f,650/$f);
		$pdf->Line(42/$f,688/$f,694/$f,688/$f);
		$pdf->Line(205/$f,71/$f,205/$f,104/$f);
		$pdf->Line(407/$f,71/$f,407/$f,104/$f);
		$pdf->Line(508/$f,71/$f,508/$f,104/$f);
		$pdf->Line(54/$f,536/$f,351/$f,536/$f);
		$pdf->Line(54/$f,555/$f,351/$f,555/$f);
		$pdf->Line(54/$f,574/$f,351/$f,574/$f);
		$pdf->Line(377/$f,533/$f,672/$f,533/$f);
		$pdf->Line(377/$f,552/$f,672/$f,552/$f);
		$pdf->Line(377/$f,571/$f,672/$f,571/$f);
		$pdf->Line(377/$f,590/$f,672/$f,590/$f);

		// azul menos claro
		$pdf->SetDrawColor(101,160,192);
		$pdf->Line(54/$f,204/$f,682/$f,204/$f);
		$pdf->Line(54/$f,316/$f,682/$f,316/$f);
		$pdf->Line(54/$f,512/$f,349/$f,512/$f);
		$pdf->Line(377/$f,512/$f,672/$f,512/$f);

		// texto negrito azul escuro
		$pdf->SetTextColor(44,83,102);
		$pdf->SetFont('Arial','B',9.5);
		$pdf->Text(208/$f,82/$f,'Emitida em:');
		$pdf->Text(410/$f,82/$f,'Competência:');
		$pdf->Text(511/$f,82/$f,'Código de Verificação:');
		$pdf->Text(54/$f,200/$f,'Tomador do(s) Serviço(s)');
		$pdf->Text(54/$f,312/$f,'Discriminação do(s) Serviço(s)');
		$pdf->Text(51/$f,354/$f,'Código de Tributação do Município (CTISS)');
		$pdf->Text(51/$f,398/$f,'Subitem Lista de Serviços LC 116/03 / Descrição:');
		$pdf->Text(51/$f,442/$f,'Cod/Município da incidência do ISSQN:');
		$pdf->Text(374/$f,442/$f,'Natureza da Operação:');
		$pdf->Text(200/$f,477/$f,'Regime Especial de Tributação:');
		$pdf->Text(54/$f,507/$f,'Valor dos serviços:');
		$pdf->Text(377/$f,507/$f,'Valor dos serviços:');
		$pdf->Text(377/$f,567/$f,'(=) Base de Cálculo:');
		$pdf->Text(51/$f,666/$f,'Outras Informações:');
		$pdf->Text(109/$f,704/$f,'Prefeitura de Belo Horizonte - Secretaria Municipal de Finanças');

		$pdf->Text(203/$f,122/$f,$PrestadorServico->RazaoSocial);
		$pdf->Text(203/$f,137/$f,'CPF/CNPJ: '.preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/','$1.$2.$3/$4-$5',$PrestadorServico->IdentificacaoPrestador->Cnpj));
		$pdf->Text(459/$f,137/$f,'Inscrição Municipal: '.preg_replace('/(\d{7})(\d{3})(\d{1})/','$1/$2-$3',$PrestadorServico->IdentificacaoPrestador->InscricaoMunicipal));
		if(isset($TomadorServico->IdentificacaoTomador->CpfCnpj->Cpf)) $pdf->Text(54/$f,218/$f,'CPF/CNPJ: '.preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/','$1.$2.$3-$4',$TomadorServico->IdentificacaoTomador->CpfCnpj->Cpf));
		if(isset($TomadorServico->IdentificacaoTomador->CpfCnpj->Cnpj)) $pdf->Text(54/$f,218/$f,'CPF/CNPJ: '.preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/','$1.$2.$3/$4-$5',$TomadorServico->IdentificacaoTomador->CpfCnpj->Cnpj));
		if(isset($TomadorServico->IdentificacaoTomador->InscricaoMunicipal)) $pdf->Text(384/$f,218/$f,'Inscrição Municipal: '.preg_replace('/(\d{7})(\d{3})(\d{1})/','$1/$2-$3',$TomadorServico->IdentificacaoTomador->InscricaoMunicipal));
		if(!isset($TomadorServico->IdentificacaoTomador->InscricaoMunicipal)) $pdf->Text(384/$f,218/$f,'Inscrição Municipal: Não Informado');
		$pdf->Text(54/$f,233/$f,$TomadorServico->RazaoSocial);
		if($InfNfse->OptanteSimplesNacional==='1')
		{
			$pdf->Text(51/$f,642/$f,'Documento emitido por ME ou EPP optante pelo Simples Nacional. Não gera direito a crédito fiscal de IPI.');
		}
		$amount=$numberFormatter->formatCurrency($Valores->ValorServicos,'BRL'); $width=$pdf->GetStringWidth($amount); $pdf->Text(349/$f-$width,507/$f,$amount);
		$amount=$numberFormatter->formatCurrency($Valores->ValorServicos,'BRL'); $width=$pdf->GetStringWidth($amount); $pdf->Text(672/$f-$width,507/$f,$amount);
		$amount=$numberFormatter->formatCurrency($Valores->BaseCalculo,'BRL'); $width=$pdf->GetStringWidth($amount); $pdf->Text(672/$f-$width,567/$f,$amount);

		// texto negrito azul escuro maior
		$pdf->SetFont('Arial','B',12.5);
		$pdf->Text(208/$f,100/$f,$dateFormatter->formatDateTime($InfNfse->DataEmissao,'medium',null));
		$pdf->Text(410/$f,100/$f,$dateFormatter->formatDateTime($InfNfse->Competencia,'medium',null));
		$pdf->Text(511/$f,100/$f,$InfNfse->CodigoVerificacao);

		// texto normal azul escuro título
		$pdf->SetFont('Arial','',14);
		$pdf->Text(157/$f,59/$f,'NFS-e - NOTA FISCAL DE SERVIÇOS ELETRÔNICA');

		// texto normal azul escuro pequeno
		$pdf->SetFont('Arial','',8.5);
		$pdf->Text(109/$f,716/$f,'Rua Espírito Santo, 605 - 2º andar - Centro - CEP: 30160-919 - Belo Horizonte MG.');
		$pdf->Text(109/$f,728/$f,'Tel.: 156 / e-mail: atendimentofinancas@pbh.gov.br');
		$pdf->Text(377/$f,528/$f,'(-) Deduções:');
		$pdf->Text(377/$f,547/$f,'(-) Desconto Incondicionado:');
		$pdf->Text(377/$f,585/$f,'(x) Alíquota:');

		$pdf->Text(290/$f,100/$f,'às '.$dateFormatter->formatDateTime($InfNfse->DataEmissao,null,'medium'));
		$pdf->Text(203/$f,152/$f,$PrestadorServico->Endereco->Endereco.', '.$PrestadorServico->Endereco->Numero.', '.(isset($PrestadorServico->Endereco->Complemento)?$PrestadorServico->Endereco->Complemento.', ':'').$PrestadorServico->Endereco->Bairro.' - Cep: '.preg_replace('/(\d{5})(\d{3})/','$1-$2',$PrestadorServico->Endereco->Cep));
		$pdf->Text(203/$f,166/$f,$l1($PrestadorServico->Endereco->CodigoMunicipio));
		$pdf->Text(459/$f,166/$f,$PrestadorServico->Endereco->Uf);
		if(isset($PrestadorServico->Contato->Telefone)) $pdf->Text(203/$f,180/$f,'Telefone: '.preg_replace('/(\d{2})(\d{4,5})(\d{4})/','($1)$2-$3',$PrestadorServico->Contato->Telefone));
		if(isset($PrestadorServico->Contato->Email)) $pdf->Text(459/$f,180/$f,'Email: '.$PrestadorServico->Contato->Email);
		$pdf->Text(54/$f,248/$f,$TomadorServico->Endereco->Endereco.', '.$TomadorServico->Endereco->Numero.', '.(isset($TomadorServico->Endereco->Complemento)?$TomadorServico->Endereco->Complemento.', ':'').$TomadorServico->Endereco->Bairro.' - Cep: '.preg_replace('/(\d{5})(\d{3})/','$1-$2',$TomadorServico->Endereco->Cep));
		$pdf->Text(54/$f,263/$f,$l1($TomadorServico->Endereco->CodigoMunicipio));
		if(isset($TomadorServico->Contato->Telefone)) $pdf->Text(54/$f,278/$f,'Telefone: '.preg_replace('/(\d{2})(\d{4,5})(\d{4})/','($1)$2-$3',$TomadorServico->Contato->Telefone));
		$pdf->Text(384/$f,263/$f,$TomadorServico->Endereco->Uf);
		if(isset($TomadorServico->Contato->Email)) $pdf->Text(384/$f,278/$f,'Email: '.$TomadorServico->Contato->Email);
		$pdf->Text(54/$f,330/$f,$Servico->Discriminacao);
		$pdf->Text(51/$f,455/$f,$Servico->CodigoMunicipio.' / '.$l1($Servico->CodigoMunicipio));
		switch($InfNfse->NaturezaOperacao)
		{
		case '1': $pdf->Text(374/$f,455/$f,'Tributação no município'); break;
		case '2': $pdf->Text(374/$f,455/$f,'Tributação fora do município'); break;
		case '3': $pdf->Text(374/$f,455/$f,'Isenção'); break;
		case '4': $pdf->Text(374/$f,455/$f,'Imune'); break;
		case '5': $pdf->Text(374/$f,455/$f,'Exigibilidade suspensa por decisão judicial'); break;
		case '6': $pdf->Text(374/$f,455/$f,'Exigibilidade suspensa por procedimento administrativo'); break;
		}
		$pdf->SetXY(48/$f,358/$f); $pdf->MultiCell(634/$f,12/$f,preg_replace('/(\d{4})(\d{1})(\d{2})(\d{2})/','$1-$2/$3-$4',$Servico->CodigoTributacaoMunicipio).' / '.$l3($Servico->CodigoTributacaoMunicipio));
		$pdf->SetXY(48/$f,402/$f); $pdf->MultiCell(634/$f,12/$f,$Servico->ItemListaServico.' / '.$l2($Servico->ItemListaServico));
		switch($InfNfse->RegimeEspecialTributacao)
		{
		case '1': $pdf->Text(380/$f,477/$f,'Microempresa municipal'); break;
		case '2': $pdf->Text(380/$f,477/$f,'Estimativa'); break;
		case '3': $pdf->Text(380/$f,477/$f,'Sociedade de profissionais'); break;
		case '4': $pdf->Text(380/$f,477/$f,'Cooperativa'); break;
		case '5': $pdf->Text(380/$f,477/$f,'MEI - Simples Nacional'); break;
		case '6': $pdf->Text(380/$f,477/$f,'ME EPP - Simples Nacional'); break;
		}
		$amount=$numberFormatter->formatCurrency(isset($Valores->ValorDeducoes)?$Valores->ValorDeducoes:'0.00','BRL'); $width=$pdf->GetStringWidth($amount); $pdf->Text(672/$f-$width,528/$f,$amount);
		$amount=$numberFormatter->formatCurrency(isset($Valores->DescontoIncondicionado)?$Valores->DescontoIncondicionado:'0.00','BRL'); $width=$pdf->GetStringWidth($amount); $pdf->Text(672/$f-$width,547/$f,$amount);
		$amount=isset($Valores->Aliquota)?$numberFormatter->formatPercentage($Valores->Aliquota):'-'; $width=$pdf->GetStringWidth($amount); $pdf->Text(672/$f-$width,585/$f,$amount);

		// texto normal azul escuro não tão pequeno
		$pdf->SetFont('Arial','',9.5);
		$pdf->Text(54/$f,531/$f,'(-) Descontos:');
		$pdf->Text(54/$f,550/$f,'(-) Retenções Federais:');
		$pdf->Text(54/$f,569/$f,'(-) ISS Retido na Fonte:');
		$amount=$numberFormatter->formatCurrency(isset($Valores->DescontoCondicionado)?$Valores->DescontoCondicionado:'0.00','BRL'); $width=$pdf->GetStringWidth($amount); $pdf->Text(349/$f-$width,531/$f,$amount);
		$amount=$numberFormatter->formatCurrency((isset($Valores->ValorPis)?$Valores->ValorPis:'0.00')+(isset($Valores->ValorCofins)?$Valores->ValorCofins:'0.00')+(isset($Valores->ValorInss)?$Valores->ValorInss:'0.00')+(isset($Valores->ValorIr)?$Valores->ValorIr:'0.00')+(isset($Valores->ValorCsll)?$Valores->ValorCsll:'0.00'),'BRL'); $width=$pdf->GetStringWidth($amount); $pdf->Text(349/$f-$width,550/$f,$amount);
		$amount=$numberFormatter->formatCurrency(isset($Valores->ValorIssRetido)?$Valores->ValorIssRetido:'0.00','BRL'); $width=$pdf->GetStringWidth($amount); $pdf->Text(349/$f-$width,569/$f,$amount);

		// texto normal vermelho grande
		$pdf->SetTextColor(195,43,21);
		$pdf->SetFont('Arial','',19);
		$pdf->Text(45/$f,95/$f,'Nº:'.preg_replace('/(\d{4})0*([1-9]\d*)/','$1/$2',$InfNfse->Numero));

		// texto negrito vermelho 
		$pdf->SetFont('Arial','B',9.5);
		$pdf->Text(54/$f,588/$f,'Valor Líquido:');
		$pdf->Text(377/$f,604/$f,'(=) Valor do ISS:');

		$amount=$numberFormatter->formatCurrency($Valores->ValorLiquidoNfse,'BRL'); $width=$pdf->GetStringWidth($amount); $pdf->Text(349/$f-$width,588/$f,$amount);
		$amount=isset($Valores->ValorIss)?$numberFormatter->formatCurrency($Valores->ValorIss,'BRL'):'-'; $width=$pdf->GetStringWidth($amount); $pdf->Text(672/$f-$width,604/$f,$amount);
		if($this->useSandbox)
		{
			$pdf->Text(51/$f,681/$f,'NFS-e gerada em ambiente de teste. NÃO TEM VALOR JURÍDICO NEM FISCAL.');
		}

		// imagens
		$pdf->Image(dirname(__FILE__).'/images/logo.gif',43/$f,116/$f);
		$pdf->Image(dirname(__FILE__).'/images/brasao.gif',43/$f,690/$f);
		$pdf->Image(dirname(__FILE__).'/images/bhnota10.jpg',610/$f,690/$f);
		if($this->useSandbox)
		{
			$pdf->Image(dirname(__FILE__).'/images/semvalidade.png',120/$f,160/$f);
		}
	}
}
