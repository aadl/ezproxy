<?php
/**
 * @file
 * Contains \Drupal\ezproxy\Controller\DefaultController.
 */
namespace Drupal\ezproxy\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\Entity\User;
use Drupal\user\UserAuthentication;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class EzproxyController extends ControllerBase
{
    protected $userauth;

    public function __construct(UserAuthentication $userauth)
    {
        $this->userauth = $userauth;
    }

    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('user.auth')
        );
    }
    public function externalAuth(Request $request)
    {
        $response = new Response();
        $method = $request->server->get('REQUEST_METHOD');

        switch ($method) {
            case 'GET':
                $username = $request->query->get('ezuser');
                $password = $request->query->get('ezpass');
                break;
            case 'POST':
                $username = $request->request->get('ezuser');
                $password = $request->request->get('ezpass');
                break;
        }
        if (!$username || !$password) {
            $response->setContent("+FAIL");
            return $response;
        }

        $uid = $this->userauth->authenticate($username, $password);

        if ($uid) {
            $user = User::load($uid);
            if ($user->hasPermission('access ezproxy content')) {
                $roles = $user->getRoles(true);
                $groups = implode('+', $roles);
                $response_string = nl2br("+OK\nezproxy_group=".$groups);
                $response->setContent($response_string);
                return $response;
            }
        }
        $response->setContent("+FAIL");
        return $response;
    }
}
