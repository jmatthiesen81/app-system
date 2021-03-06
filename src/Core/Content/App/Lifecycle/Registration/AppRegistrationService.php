<?php declare(strict_types=1);

namespace Swag\SaasConnect\Core\Content\App\Lifecycle\Registration;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Swag\SaasConnect\Core\Content\App\AppEntity;
use Swag\SaasConnect\Core\Content\App\Exception\AppRegistrationException;
use Swag\SaasConnect\Core\Content\App\Manifest\Manifest;
use Swag\SaasConnect\Core\Framework\ShopId\ShopIdProvider;

class AppRegistrationService
{
    /**
     * @var HandshakeFactory
     */
    private $handshakeFactory;

    /**
     * @var Client
     */
    private $httpClient;

    /**
     * @var EntityRepositoryInterface
     */
    private $appRepository;

    /**
     * @var string
     */
    private $shopUrl;

    /**
     * @var ShopIdProvider
     */
    private $shopIdProvider;

    public function __construct(
        HandshakeFactory $handshakeFactory,
        Client $httpClient,
        EntityRepositoryInterface $appRepository,
        string $shopUrl,
        ShopIdProvider $shopIdProvider
    ) {
        $this->handshakeFactory = $handshakeFactory;
        $this->httpClient = $httpClient;
        $this->appRepository = $appRepository;
        $this->shopUrl = $shopUrl;
        $this->shopIdProvider = $shopIdProvider;
    }

    public function registerApp(Manifest $manifest, string $id, string $secretAccessKey, Context $context): void
    {
        if (!$manifest->getSetup()) {
            return;
        }

        $appResponse = $this->registerWithApp($manifest, $id);

        $secret = $appResponse['secret'];
        $confirmationUrl = $appResponse['confirmation_url'];

        $this->saveAppSecret($id, $context, $secret);

        $this->confirmRegistration($id, $context, $secret, $secretAccessKey, $confirmationUrl);
    }

    /**
     * @return array<string,string>
     */
    private function registerWithApp(Manifest $manifest, string $appId): array
    {
        $handshake = $this->handshakeFactory->create($manifest, $appId);

        $request = $handshake->assembleRequest();
        $response = $this->httpClient->send($request);

        return $this->parseResponse($handshake, $response);
    }

    private function saveAppSecret(string $id, Context $context, string $secret): void
    {
        $update = ['id' => $id, 'appSecret' => $secret];

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($update): void {
            $this->appRepository->update([$update], $context);
        });
    }

    private function confirmRegistration(
        string $id,
        Context $context,
        string $secret,
        string $secretAccessKey,
        string $confirmationUrl
    ): void {
        $payload = $this->getConfirmationPayload($id, $secretAccessKey, $context);

        $signature = $this->signPayload($payload, $secret);

        $this->httpClient->post($confirmationUrl, [
            'headers' => [
                'shopware-shop-signature' => $signature,
            ],
            'json' => $payload,
        ]);
    }

    /**
     * @return array<string,string>
     */
    private function parseResponse(AppHandshakeInterface $handshake, ResponseInterface $response): array
    {
        $data = \json_decode($response->getBody()->getContents(), true);

        $proof = $data['proof'] ?? '';
        if (!hash_equals($handshake->fetchAppProof(), trim($proof))) {
            throw new AppRegistrationException('The app provided a invalid response');
        }

        return $data;
    }

    /**
     * @return array<string,string>
     */
    private function getConfirmationPayload(string $id, string $secretAccessKey, Context $context): array
    {
        $app = $this->getApp($id, $context);

        return [
            'apiKey' => $app->getIntegration()->getAccessKey(),
            'secretKey' => $secretAccessKey,
            'timestamp' => (string) (new \DateTime())->getTimestamp(),
            'shopUrl' => $this->shopUrl,
            'shopId' => $this->shopIdProvider->getShopId($id),
        ];
    }

    /**
     * @param array<string,string> $body
     */
    private function signPayload(array $body, string $secret): string
    {
        return hash_hmac('sha256', (string) \json_encode($body), $secret);
    }

    private function getApp(string $id, Context $context): AppEntity
    {
        $criteria = new Criteria([$id]);
        $criteria->addAssociation('integration');

        /** @var AppEntity $app */
        $app = $this->appRepository->search($criteria, $context)->first();

        return $app;
    }
}
