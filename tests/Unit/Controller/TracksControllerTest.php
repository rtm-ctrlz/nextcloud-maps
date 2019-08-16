<?php
/**
 * Nextcloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2019
 */

namespace OCA\Maps\Controller;

use \OCA\Maps\AppInfo\Application;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCA\Maps\Service\GeophotoService;
use OCA\Maps\Service\PhotofilesService;
use OCA\Maps\Service\TracksService;
use OCA\Maps\Service\DevicesService;
use OCA\Maps\DB\GeophotoMapper;
use OCP\AppFramework\Http\TemplateResponse;


class TracksControllerTest extends \PHPUnit\Framework\TestCase {
    private $appName;
    private $request;
    private $contacts;

    private $container;
    private $config;
    private $app;

    private $tracksController;
    private $tracksController2;
    private $utilsController;

    private $tracksService;

    public static function setUpBeforeClass(): void {
        $app = new Application();
        $c = $app->getContainer();

        $user = $c->getServer()->getUserManager()->get('test');
        $user2 = $c->getServer()->getUserManager()->get('test2');
        $user3 = $c->getServer()->getUserManager()->get('test3');
        $group = $c->getServer()->getGroupManager()->get('group1test');
        $group2 = $c->getServer()->getGroupManager()->get('group2test');

        // CREATE DUMMY USERS
        if ($user === null) {
            $u1 = $c->getServer()->getUserManager()->createUser('test', 'tatotitoTUTU');
            $u1->setEMailAddress('toto@toto.net');
        }
        if ($user2 === null) {
            $u2 = $c->getServer()->getUserManager()->createUser('test2', 'plopinoulala000');
        }
        if ($user2 === null) {
            $u3 = $c->getServer()->getUserManager()->createUser('test3', 'yeyeahPASSPASS');
        }
        if ($group === null) {
            $c->getServer()->getGroupManager()->createGroup('group1test');
            $u1 = $c->getServer()->getUserManager()->get('test');
            $c->getServer()->getGroupManager()->get('group1test')->addUser($u1);
        }
        if ($group2 === null) {
            $c->getServer()->getGroupManager()->createGroup('group2test');
            $u2 = $c->getServer()->getUserManager()->get('test2');
            $c->getServer()->getGroupManager()->get('group2test')->addUser($u2);
        }
    }

    protected function setUp(): void {
        $this->appName = 'maps';
        $this->request = $this->getMockBuilder('\OCP\IRequest')
            ->disableOriginalConstructor()
            ->getMock();
        $this->contacts = $this->getMockBuilder('OCP\Contacts\IManager')
            ->disableOriginalConstructor()
            ->getMock();

        $this->app = new Application();
        $this->container = $this->app->getContainer();
        $c = $this->container;
        $this->config = $c->query('ServerContainer')->getConfig();

        $this->rootFolder = $c->query('ServerContainer')->getRootFolder();

        $this->tracksService = new TracksService(
            $c->query('ServerContainer')->getLogger(),
            $c->query('ServerContainer')->getL10N($c->query('AppName')),
            $this->rootFolder,
            $c->query('ServerContainer')->getShareManager()
        );

        $this->tracksController = new TracksController(
            $this->appName,
            $this->request,
            'test',
            $c->query('ServerContainer')->getUserFolder('test'),
            $c->query('ServerContainer')->getConfig(),
            $c->query('ServerContainer')->getShareManager(),
            $c->getServer()->getAppManager(),
            $c->getServer()->getUserManager(),
            $c->getServer()->getGroupManager(),
            $c->query('ServerContainer')->getL10N($c->query('AppName')),
            $c->query('ServerContainer')->getLogger(),
            $this->tracksService
        );

        $this->tracksController2 = new TracksController(
            $this->appName,
            $this->request,
            'test2',
            $c->query('ServerContainer')->getUserFolder('test2'),
            $c->query('ServerContainer')->getConfig(),
            $c->query('ServerContainer')->getShareManager(),
            $c->getServer()->getAppManager(),
            $c->getServer()->getUserManager(),
            $c->getServer()->getGroupManager(),
            $c->query('ServerContainer')->getL10N($c->query('AppName')),
            $c->query('ServerContainer')->getLogger(),
            $this->tracksService
        );

        $this->utilsController = new UtilsController(
            $this->appName,
            $this->request,
            'test',
            $c->query('ServerContainer')->getUserFolder('test'),
            $c->query('ServerContainer')->getConfig(),
            $c->getServer()->getAppManager()
        );

        $userfolder = $this->container->query('ServerContainer')->getUserFolder('test');

        // delete first
        if ($userfolder->nodeExists('dir/testFile1.gpx')) {
            echo "DELETE\n";
            $file = $userfolder->get('dir/testFile1.gpx');
            $file->delete();
        }
        // delete db
        $qb = $c->query('ServerContainer')->getDatabaseConnection()->getQueryBuilder();
        $qb->delete('maps_tracks')
            ->where(
                $qb->expr()->eq('user_id', $qb->createNamedParameter('test', IQueryBuilder::PARAM_STR))
            );
        $req = $qb->execute();
        $qb = $qb->resetQueryParts();
    }

    public static function tearDownAfterClass(): void {
        //$app = new Application();
        //$c = $app->getContainer();
        //$user = $c->getServer()->getUserManager()->get('test');
        //$user->delete();
        //$user = $c->getServer()->getUserManager()->get('test2');
        //$user->delete();
        //$user = $c->getServer()->getUserManager()->get('test3');
        //$user->delete();
        //$c->getServer()->getGroupManager()->get('group1test')->delete();
        //$c->getServer()->getGroupManager()->get('group2test')->delete();
    }

    protected function tearDown(): void {
        // in case there was a failure and something was not deleted
        $c = $this->app->getContainer();

        $userfolder = $this->container->query('ServerContainer')->getUserFolder('test');
        // delete files
        if ($userfolder->nodeExists('testFile1.gpx')) {
            $file = $userfolder->get('testFile1.gpx');
            $file->delete();
        }
        // delete db
        $qb = $c->query('ServerContainer')->getDatabaseConnection()->getQueryBuilder();
        $qb->delete('maps_tracks')
            ->where(
                $qb->expr()->eq('user_id', $qb->createNamedParameter('test', IQueryBuilder::PARAM_STR))
            );
        $req = $qb->execute();
        $qb = $qb->resetQueryParts();
    }

    public function testAddGetTracks() {
        $c = $this->app->getContainer();

        $userfolder = $this->container->query('ServerContainer')->getUserFolder('test');

        $filename = 'tests/test_files/testFile1.gpx';
        $content1 = file_get_contents($filename);
        $file = $userfolder->newFile('testFile1.gpxx');
        $file->putContent($content1);
        //$file->touch();

        $file = $userfolder->get('testFile1.gpxx');
        $file->move($userfolder->getPath().'/testFile1.gpx');
        //echo 'I MOVE TO '.$userfolder->getPath().'/testFile1.gpx'."\n";
        $file->touch();

        $resp = $this->tracksController->getTracks();
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $this->assertEquals(1, count($data));
        //var_dump($data);

        $this->tracksService->rescan('test');

        $resp = $this->tracksController->getTracks();
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $this->assertEquals(1, count($data));
    }

}
