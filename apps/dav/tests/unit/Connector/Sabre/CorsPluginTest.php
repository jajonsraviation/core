<?php
/**
 * @author Vincent Petry <pvince81@owncloud.com>
 *
 * @copyright Copyright (c) 2018, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\DAV\Tests\unit\Connector\Sabre;

use OCP\IUserSession;
use OCP\IUser;
use OCP\IConfig;
use Sabre\DAV\ServerPlugin;

class CorsPluginTest extends \Test\TestCase {

	/**
	 * @var \Sabre\DAV\Server
	 */
	private $server;

	/**
	 * @var \OCA\DAV\Connector\Sabre\CorsPlugin
	 */
	private $plugin;

	/**
	 * @var IUserSession | \PHPUnit_Framework_MockObject_MockObject
	 */
	private $userSession;

	/**
	 * @var IConfig | \PHPUnit_Framework_MockObject_MockObject
	 */
	private $config;

	public function setUp() {
		parent::setUp();
		$this->server = new \Sabre\DAV\Server();

		$this->server->sapi = $this->getMockBuilder(\stdclass::class)
			->setMethods(['sendResponse'])
			->getMock();
		$this->server->sapi->expects($this->once())->method('sendResponse')->with($this->server->httpResponse);

		$this->server->httpRequest->setMethod('OPTIONS');
		$this->server->httpRequest->setUrl('/owncloud/remote.php/dav/files/user1/target/path');

		$this->userSession = $this->createMock(IUserSession::class);

		$this->config = $this->createMock(IConfig::class);
		$this->overwriteService('AllConfig', $this->config);

		$this->plugin = new \OCA\DAV\Connector\Sabre\CorsPlugin($this->userSession);

		$extraMethodPlugin = $this->createMock(ServerPlugin::class);
		$extraMethodPlugin->method('getHTTPMethods')
			->with('owncloud/remote.php/dav/files/user1/target/path')
			->willReturn(['EXTRA']);
		$extraMethodPlugin->method('getFeatures')->willReturn([]);

		$this->server->addPlugin($extraMethodPlugin);
	}

	public function tearDown() {
		$this->restoreService('AllConfig');
	}

	public function optionsCases() {
		$allowedDomains = '["https://requesterdomain.tld", "http://anotherdomain.tld"]';

		$allowedHeaders = [
			'authorization',
			'OCS-APIREQUEST',
			'Origin',
			'X-Requested-With',
			'Content-Type',
			'Access-Control-Allow-Origin',
			'X-OC-Mtime',
			'OC-Checksum',
			'OC-Total-Length',
			'Depth',
			'Destination',
			'Overwrite',
		];
		$allowedMethods = [
			'GET',
			'OPTIONS',
			'POST',
			'PUT',
			'DELETE',
			'MKCOL',
			'PROPFIND',
			'PATCH',
			'PROPPATCH',
			'REPORT',
			'HEAD',
			'COPY',
			'MOVE',
			'EXTRA',
		];

		return [
			// OPTIONS headers
			[
				$allowedDomains,
				false,
				[
					'Origin' => 'https://requesterdomain.tld',
				],
				200,
				[
					'Access-Control-Allow-Headers' => implode(',', $allowedHeaders),
					'Access-Control-Allow-Origin' => '*',
					'Access-Control-Allow-Methods' => implode(',', $allowedMethods),
				],
				false
			],
			// OPTIONS headers with user
			[
				$allowedDomains,
				true,
				[
					'Origin' => 'https://requesterdomain.tld',
					'Authorization' => 'abc',
				],
				200,
				[
					'Access-Control-Allow-Headers' => implode(',', $allowedHeaders),
					'Access-Control-Allow-Origin' => 'https://requesterdomain.tld',
					'Access-Control-Allow-Methods' => implode(',', $allowedMethods),
				],
				true
			],
			// OPTIONS headers no user
			[
				$allowedDomains,
				false,
				[
					'Origin' => 'https://requesterdomain.tld',
					'Authorization' => 'abc',
				],
				200,
				[
					'Access-Control-Allow-Headers' => null,
					'Access-Control-Allow-Origin' => null,
					'Access-Control-Allow-Methods' => null,
				],
				true
			],
			// OPTIONS headers domain not allowed
			[
				'[]',
				true,
				[
					'Origin' => 'https://requesterdomain.tld',
					'Authorization' => 'abc',
				],
				200,
				[
					'Access-Control-Allow-Headers' => null,
					'Access-Control-Allow-Origin' => null,
					'Access-Control-Allow-Methods' => null,
				],
				true
			],
			// OPTIONS headers not allowed but no cross-domain
			[
				'[]',
				true,
				[
					'Origin' => 'https://requesterdomain.tld',
					'Authorization' => 'abc',
				],
				200,
				[
					'Access-Control-Allow-Headers' => null,
					'Access-Control-Allow-Origin' => null,
					'Access-Control-Allow-Methods' => null,
				],
				true
			],
			// OPTIONS headers allowed but no cross-domain
			[
				'["currentdomain.tld:8080"]',
				true,
				[
					'Origin' => 'https://currentdomain.tld:8080',
					'Authorization' => 'abc',
				],
				200,
				[
					'Access-Control-Allow-Headers' => null,
					'Access-Control-Allow-Origin' => null,
					'Access-Control-Allow-Methods' => null,
				],
				true
			],
			// OPTIONS headers allowed, cross-domain through different port
			[
				'["https://currentdomain.tld:8443"]',
				true,
				[
					'Origin' => 'https://currentdomain.tld:8443',
					'Authorization' => 'abc',
				],
				200,
				[
					'Access-Control-Allow-Headers' => implode(',', $allowedHeaders),
					'Access-Control-Allow-Origin' => 'https://currentdomain.tld:8443',
					'Access-Control-Allow-Methods' => implode(',', $allowedMethods),
				],
				true
			],
			// no Origin header
			[
				$allowedDomains,
				true,
				[
				],
				200,
				[
					'Access-Control-Allow-Headers' => null,
					'Access-Control-Allow-Origin' => null,
					'Access-Control-Allow-Methods' => null,
				],
				true
			],
		];
	}

	/**
	 * @dataProvider optionsCases
	 */
	public function testOptionsHeaders($allowedDomains, $hasUser, $requestHeaders, $expectedStatus, $expectedHeaders, $expectDavHeaders = false) {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('someuser');

		if ($hasUser) {
			$this->userSession->method('getUser')->willReturn($user);
		} else {
			$this->userSession->method('getUser')->willReturn(null);
		}

		$this->config->method('getUserValue')
			->with('someuser', 'core', 'domains')
			->willReturn($allowedDomains);

		$this->server->httpRequest->setHeaders($requestHeaders);
		$this->server->httpRequest->setAbsoluteUrl('https://currentdomain.tld:8080/owncloud/remote.php/dav/files/user1/target/path');
		$this->server->httpRequest->setUrl('/owncloud/remote.php/dav/files/user1/target/path');

		$this->server->addPlugin($this->plugin);
		$this->server->exec();

		$this->assertEquals($expectedStatus, $this->server->httpResponse->getStatus());

		foreach ($expectedHeaders as $headerKey => $headerValue) {
			if ($headerValue !== null) {
				$this->assertTrue($this->server->httpResponse->hasHeader($headerKey), "Response header \"$headerKey\" exists");
			} else {
				$this->assertFalse($this->server->httpResponse->hasHeader($headerKey), "Response header \"$headerKey\" does not exist");
			}
			$this->assertEquals($headerValue, $this->server->httpResponse->getHeader($headerKey));
		}

		// if it has DAV headers, it means we did not bypass further processing
		$this->assertEquals($expectDavHeaders, $this->server->httpResponse->hasHeader('DAV'));
	}

}
