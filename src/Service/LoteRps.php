<?php namespace NFse\Service;

use Exception;
use NFse\Helpers\Utils;
use NFse\Helpers\XML;
use NFse\Models\Settings;
use NFse\Signature\Dom;
use NFse\Signature\Subscriber;
use NFse\Soap\EnvioLoteRps;
use NFse\Soap\ErrorMsg;
use NFse\Soap\Soap;
use NFse\Service\Rps;

class LoteRps
{
    private $xSoap;
    private $loteRps;
    private $xmlLote;
    private $subscriber;

    /**
     * construtor
     *
     * @param NFse\Models\Settings;
     * @param string
     */
    public function __construct(Settings $settings, string $numLote)
    {

        $this->xSoap = new Soap($settings, $settings->issuer->codMun == 3106200 ? 'GerarNfseRequest' : 'GerarNfse');
        $this->loteRps = new XmlRps($settings, $numLote);

        $this->subscriber = new Subscriber($settings);

        //tenta carregar os certificados
        try {
            $this->subscriber->loadPFX();
        } catch (Exception $e) {

            throw $e;
        }
    }

    /**
     * adiciona uma RPS assinada ao lote
     * @param string
     */
    public function addRps(string $signedRps): void
    {
        $this->loteRps->addRps($signedRps);
    }

    /**
     * retorna o lote pronto para envio
     */
    public function sendLote($signTag = null): object
    {
        $xmlLote = Utils::xmlFilter($this->loteRps->getLoteRps());

        if ($settings->issuer->codMun != 3147105) {
            //tenta assinar o lote
            try {
                $signedLote = $this->subscriber->assina($xmlLote, $signTag);
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
            $this->xmlLote = $signedLote;
        }
        //envia o request para a PBH
        try {
            $this->xSoap->setXML($signedLote);
            $wsResponse = $this->xSoap->__soapCall();
        } catch (Exception $e) {

            throw new Exception($e->getMessage());
        }
        //carrega o xml de resposta para um object
        $xmlResponse = simplexml_load_string($wsResponse->outputXML);

        //identifica o retorno e faz o processamento nescessário
        if (is_object($xmlResponse) && isset($xmlResponse->ListaMensagemRetornoLote) || isset($xmlResponse->ListaMensagemRetorno)) {
            $wsError = new ErrorMsg($xmlResponse);
            return (object)[
                'success' => false,
                'response' => (object)$wsError->getWsResponse(),
            ];
        } else {
            $wsLote = new EnvioLoteRps($wsResponse);
            return (object)[
                'success' => true,
                'response' => (object)$wsLote->getDadosLote(),
            ];
        }
    }

    public function sendLoteQuasar($data): object
    {
        //carrega xml e seta valores
        $xml = XML::load('nfseEnvioQuasar')
            ->set('InfDeclaracaoPrestacaoServico', $data)
            ->filter()->save();
        $format = '<?xml version="1.0" encoding="UTF-8"?>' . $xml;

        //remove sujeiras do xml
        $order = ["\r\n", "\n", "\r", "\t"];
        $result = str_replace($order, '', htmlspecialchars($format, ENT_QUOTES | ENT_XML1));

        //envia o request para soap
        try {
            $this->xSoap->setXML($result);
            $wsResponse = $this->xSoap->__soapCall();
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        $xmlResponse = simplexml_load_string($wsResponse->return);

        //identifica o retorno e faz o processamento nescessário
        if (is_object($xmlResponse) && isset($xmlResponse->ListaMensagemRetornoLote) || isset($xmlResponse->ListaMensagemRetorno)) {
            $wsError = new ErrorMsg($xmlResponse);
            return (object)[
                'success' => false,
                'response' => (object)$wsError->getWsResponse(),
            ];
        } else {
            $wsLote = new EnvioLoteRps($wsResponse);
            return (object)[
                'success' => true,
                'response' => (object)$wsLote->getDadosLote(),
            ];
        }
    }


    public function getXMLLote()
    {
        return $this->xmlLote;
    }
}
