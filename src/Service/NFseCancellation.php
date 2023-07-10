<?php namespace NFse\Service;

use Exception;
use NFse\Helpers\Utils;
use NFse\Models\Settings;
use NFse\Signature\Subscriber;
use NFse\Soap\CancelamentoNFs;
use NFse\Soap\ErrorMsg;
use NFse\Soap\Soap;

class NFseCancellation extends ConsultBase
{
    private $xSoap;
    private $numNFs;
    private $settings;
    private $subscriber;

    /**
     * constroi o xml de consulta
     *
     * @param NFse\Models\Settings;
     * @param object
     */
    public function __construct(Settings $settings, object $parameters)
    {
        parent::__construct();

        $this->subscriber = new Subscriber($settings);

        $this->numNFs = $parameters->numerNFse;
        $this->settings = $settings;

        $parameters->file = $settings->issuer->codMun === 3147105 ? 'cancelamentoNFsQuasar' : 'cancelamentoNFs';
        $method = $settings->issuer->codMun === 3147105 ? 'CancelarNfse' : 'CancelarNfseRequest';
        $this->xSoap = new Soap($settings, $method);
        $this->callConsultation($settings, $parameters);
    }

    /**
     * envia o request de cancelamento da nota
     */
    public function sendConsultation(): object
    {
        //recupera e assina o xml de cancelamento
        $xmlCancel = $this->getXML();

        try {
            $this->subscriber->loadPFX();
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        $sxlCancel = $xmlCancel;
        if ($this->settings->issuer->codMun != 3147105) {
            $sxlCancel = $this->subscriber->assina($xmlCancel, 'InfPedidoCancelamento');
        }
        if ($this->settings->issuer->codMun === 3147105) {
            //remove sujeiras do xml
            $order = ["\r\n", "\n", "\r", "\t"];
            $xml = str_replace($order, '', htmlspecialchars($sxlCancel, ENT_QUOTES | ENT_XML1));

        }
        if ($this->settings->issuer->codMun != 3147105) {
            //faz um pequeno hack trocando a posição da tag de assinatura devido a um erro no parser do webservice
            $xml = new \DOMDocument('1.0', 'utf-8');
            $xml->preserveWhiteSpace = false;
            $xml->loadXML($sxlCancel);

            $signature = $xml->getElementsByTagName('Signature')[0];
            $xml->documentElement->removeChild($xml->getElementsByTagName('Signature')[0]);
            $xml->getElementsByTagName('Pedido')[0]->appendChild($signature);
            $xml->saveXML();
        }

        //envia a chamada para o SOAP
        try {
            $this->xSoap->setXML($xml);
            $wsResponse = $this->xSoap->__soapCall();
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        //carrega o xml de resposta para um object

        $xmlResponse = isset($wsResponse->outputXML) ? simplexml_load_string($wsResponse->outputXML) : simplexml_load_string($wsResponse->return);
        //identifica o retorno e faz o processamento nescessário
        if (is_object($xmlResponse) && isset($xmlResponse->ListaMensagemRetorno)) {
            $wsError = new ErrorMsg($xmlResponse);
            $messages = $wsError->getMessages('ListaMensagemRetorno');

            return (object)$this->errors = ($messages) ? $messages : $wsError->getError();
        } else {
            $wsLote = new CancelamentoNFs($wsResponse);
            $dataCancel = $wsLote->getDataCancelamento();

            return (object)$dataCancel;
        }
    }
}
