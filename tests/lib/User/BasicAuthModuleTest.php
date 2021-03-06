<?php
/**
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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


namespace Test\User;


use OC\User\BasicAuthModule;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUser;
use OCP\IUserManager;
use Test\TestCase;

class BasicAuthModuleTest extends TestCase {

	/** @var IUserManager | \PHPUnit_Framework_MockObject_MockObject */
	private $manager;
	/** @var IRequest | \PHPUnit_Framework_MockObject_MockObject */
	private $request;
	/** @var IUser | \PHPUnit_Framework_MockObject_MockObject */
	private $user;
	/** @var ISession | \PHPUnit_Framework_MockObject_MockObject */
	private $session;

	public function setUp() {
		parent::setUp();
		$this->manager = $this->createMock(IUserManager::class);
		$this->request = $this->createMock(IRequest::class);
		$this->session = $this->createMock(ISession::class);

		$this->user = $this->createMock(IUser::class);
		$this->user->expects($this->any())->method('getUID')->willReturn('user1');

		$this->manager->expects($this->any())->method('checkPassword')
			->willReturnMap([
				['user1', '123456', $this->user],
				['user@example.com', '123456', $this->user],
				['user2', '123456', null],
				['not-unique@example.com', '123456', null],
				['unique@example.com', '123456', null],
			]);

		$this->manager->expects($this->any())->method('getByEmail')
			->willReturnMap([
				['not-unique@example.com', [$this->user, $this->user]],
				['unique@example.com', [$this->user]],
				['user2', []]
			]);

	}

	/**
	 * @dataProvider providesCredentials
	 * @param mixed $expectedResult
	 * @param string $userId
	 */
	public function testAuth($expectedResult, $userId) {

		$this->session
			->method('exists')
			->with('app_password')
			->willReturn(false);

		$module = new BasicAuthModule($this->manager, $this->session);
		$this->request->server = [
			'PHP_AUTH_USER' => $userId,
			'PHP_AUTH_PW' => '123456',
		];
		if ($expectedResult instanceof \Exception) {
			$this->expectException(get_class($expectedResult));
			$this->expectExceptionMessage($expectedResult->getMessage());
		}
		$this->assertEquals($expectedResult ? $this->user : null, $module->auth($this->request));
	}

	public function testAppPassword() {

		$this->session
			->expects($this->once())
			->method('exists')
			->with('app_password')
			->willReturn(true);

		$this->manager
			->expects($this->never())
			->method('checkPassword');

		$module = new BasicAuthModule($this->manager, $this->session);
		$this->request->server = [
			'PHP_AUTH_USER' => 'user',
			'PHP_AUTH_PW' => 'app-pass-word',
		];
		$this->assertEquals(null, $module->auth($this->request));
	}

	public function testGetUserPassword() {
		$module = new BasicAuthModule($this->manager, $this->session);
		$this->request->server = [
			'PHP_AUTH_USER' => 'user1',
			'PHP_AUTH_PW' => '123456',
		];
		$this->assertEquals('123456', $module->getUserPassword($this->request));

		$this->request->server = [];
		$this->assertEquals('', $module->getUserPassword($this->request));
	}

	public function providesCredentials() {

		return [
			'no user is' => [false, ''],
			'user1 can login' => [true, 'user1'],
			'user1 can login with email' => [true, 'user@example.com'],
			'unique email can login' => [true, 'unique@example.com'],
			'not unique email can not login' => [new \Exception('Invalid credentials'), 'not-unique@example.com'],
			'user2 is not known' => [new \Exception('Invalid credentials'), 'user2'],
		];
	}
}
