<?php declare(strict_types=1);

namespace Swag\SaasConnect\Test\Core\Content\App\Api;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Swag\SaasConnect\Core\Content\App\Aggregate\ActionButton\ActionButtonEntity;
use Swag\SaasConnect\Core\Framework\ShopId\ShopIdProvider;
use Swag\SaasConnect\Test\AppSystemTestBehaviour;
use Swag\SaasConnect\Test\StorefrontAppRegistryTestBehaviour;

class AppActionControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AdminApiTestBehaviour;
    use AppSystemTestBehaviour;
    use StorefrontAppRegistryTestBehaviour;

    public function testGetActionsPerViewEmpty(): void
    {
        $url = '/api/v' . PlatformRequest::API_VERSION . '/app-system/action-button/product/index';
        $this->getBrowser()->request('GET', $url);
        $response = json_decode($this->getBrowser()->getResponse()->getContent(), true);

        static::assertEquals(200, $this->getBrowser()->getResponse()->getStatusCode());
        static::assertArrayHasKey('actions', $response);
        static::assertEmpty($response['actions']);
    }

    public function testGetActionsPerView(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../Manifest/_fixtures/test');
        $url = '/api/v' . PlatformRequest::API_VERSION . '/app-system/action-button/order/detail';
        $this->getBrowser()->request('GET', $url);

        static::assertEquals(200, $this->getBrowser()->getResponse()->getStatusCode());

        $result = json_decode($this->getBrowser()->getResponse()->getContent(), true);
        static::assertArrayHasKey('actions', $result);

        $result = $result['actions'];
        static::assertCount(1, $result);
        static::assertTrue(Uuid::isValid($result[0]['id']));
        unset($result[0]['id']);

        static::assertEquals([
            [
                'app' => 'SwagApp',
                'label' => [
                    'en-GB' => 'View Order',
                    'de-DE' => 'Zeige Bestellung',
                ],
                'action' => 'viewOrder',
                'url' => 'https://swag-test.com/your-order',
                'openNewTab' => true,
                'icon' => base64_encode(file_get_contents(__DIR__ . '/../Manifest/_fixtures/test/icon.png')),
            ],
        ], $result);
    }

    public function testRunAction(): void
    {
        $appServerMock = $this->getContainer()->get(MockHandler::class);

        /** @var EntityRepositoryInterface $actionRepo */
        $actionRepo = $this->getContainer()->get('saas_app_action_button.repository');
        $this->loadAppsFromDir(__DIR__ . '/../Manifest/_fixtures/test');

        $criteria = (new Criteria())
            ->setLimit(1)
            ->addAssociation('app')
            ->addAssociation('app.integration');

        $action = $actionRepo->search($criteria, Context::createDefaultContext());
        /** @var ActionButtonEntity $action */
        $action = $action->first();

        $url = '/api/v' . PlatformRequest::API_VERSION . '/app-system/action-button/run/' . $action->getId();

        $ids = [Uuid::randomHex()];
        $postData = [
            'ids' => $ids,
        ];

        $appServerMock->append(new Response(200));
        $this->getBrowser()->request('POST', $url, [], [], [], json_encode($postData));

        static::assertEquals(200, $this->getBrowser()->getResponse()->getStatusCode());

        $request = $appServerMock->getLastRequest();

        static::assertEquals('POST', $request->getMethod());
        $body = $request->getBody()->getContents();
        static::assertJson($body);
        $data = json_decode($body, true);

        /** @var ShopIdProvider $shopIdProvider */
        $shopIdProvider = $this->getContainer()->get(ShopIdProvider::class);

        $expectedSource = [
            'url' => getenv('APP_URL'),
            'appVersion' => $action->getApp()->getVersion(),
            'shopId' => $shopIdProvider->getShopId($action->getAppId()),
        ];
        $expectedData = [
            'ids' => $ids,
            'action' => $action->getAction(),
            'entity' => $action->getEntity(),
        ];

        static::assertEquals($expectedSource, $data['source']);
        static::assertEquals($expectedData, $data['data']);
        static::assertNotEmpty($data['meta']['timestamp']);
        static::assertTrue(Uuid::isValid($data['meta']['reference']));
    }

    public function testRunActionEmpty(): void
    {
        $appServerMock = $this->getContainer()->get(MockHandler::class);

        /** @var EntityRepositoryInterface $actionRepo */
        $actionRepo = $this->getContainer()->get('saas_app_action_button.repository');
        $this->loadAppsFromDir(__DIR__ . '/../Manifest/_fixtures/test');

        $criteria = (new Criteria())
            ->setLimit(1)
            ->addAssociation('app');

        $action = $actionRepo->search($criteria, Context::createDefaultContext());
        /** @var ActionButtonEntity $action */
        $action = $action->first();

        $url = '/api/v' . PlatformRequest::API_VERSION . '/app-system/action-button/run/' . $action->getId();

        $postData = ['ids' => []];

        $appServerMock->append(new Response(200));
        $this->getBrowser()->request('POST', $url, [], [], [], json_encode($postData));

        static::assertEquals(200, $this->getBrowser()->getResponse()->getStatusCode());

        $request = $appServerMock->getLastRequest();

        static::assertEquals('POST', $request->getMethod());
        $body = $request->getBody()->getContents();
        static::assertJson($body);
        $data = json_decode($body, true);

        $expectedData = [
            'ids' => [],
            'action' => $action->getAction(),
            'entity' => $action->getEntity(),
        ];

        static::assertEquals($expectedData, $data['data']);
    }

    public function testRunInvalidAction(): void
    {
        $url = '/api/v' . PlatformRequest::API_VERSION . '/app-system/action-button/run/' . Uuid::randomHex();

        $postData = ['ids' => []];

        $this->getBrowser()->request('POST', $url, [], [], [], json_encode($postData));

        static::assertEquals(404, $this->getBrowser()->getResponse()->getStatusCode());
    }

    public function testGetModules(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../Manifest/_fixtures/test');
        $url = '/api/v' . PlatformRequest::API_VERSION . '/app-system/modules';
        $this->getBrowser()->request('GET', $url);

        static::assertEquals(200, $this->getBrowser()->getResponse()->getStatusCode());

        $result = json_decode($this->getBrowser()->getResponse()->getContent(), true);

        // the query strings of the sources contain non-deterministic values like timestamps
        // they are validated in `\Swag\SaasConnect\Test\Core\Content\App\Action\ModuleLoaderTest::validateSources`
        // so we remove them here and don't check them
        $result = $this->removeQueryStringsFromResult($result);

        static::assertEquals([
            'modules' => [
                [
                    'name' => 'SwagApp',
                    'label' => [
                        'en-GB' => 'Swag App Test',
                        'de-DE' => 'Swag App Test',
                    ],
                    'modules' => [
                        [
                            'label' => [
                                'en-GB' => 'My first own module',
                                'de-DE' => 'Mein erstes eigenes Modul',
                            ],
                            'source' => 'https://test.com',
                            'name' => 'first-module',
                        ],
                        [
                            'label' => [
                                'en-GB' => 'My second module',
                            ],
                            'source' => 'https://test.com/second',
                            'name' => 'second-module',
                        ],
                    ],
                ],
            ],
        ], $result);
    }

    private function removeQueryStringsFromResult(array $result): array
    {
        $queryString = parse_url($result['modules'][0]['modules'][0]['source'], PHP_URL_QUERY);
        $result['modules'][0]['modules'][0]['source'] = \str_replace(
            '?' . $queryString,
            '',
            $result['modules'][0]['modules'][0]['source']
        );

        $queryString = parse_url($result['modules'][0]['modules'][1]['source'], PHP_URL_QUERY);
        $result['modules'][0]['modules'][1]['source'] = \str_replace(
            '?' . $queryString,
            '',
            $result['modules'][0]['modules'][1]['source']
        );

        return $result;
    }
}
