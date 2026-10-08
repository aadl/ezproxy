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
use Symfony\Component\HttpFoundation\RedirectResponse;

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

  public function authenticate(Request $request) {
    $uid = \Drupal::currentUser()->id();
    $ezproxy_url = $request->query->get('url');
    $user = User::load($uid);
    // check if user is logged in and if they have ezproxy permissions
    if ($user->isAuthenticated()) {
      if ($user->hasPermission('access ezproxy content')) {
        return $this->redirectEzproxy( $pid = $user->get('field_patron_id')->value, $ezproxy_url);
      }
      // no permission for ezproxy, redirect to account page
      // should flash message for card expired or no card on account
      return new RedirectResponse(\Drupal\Core\Url::fromRoute('user.page')->toString());
    }

    $session = $request->getSession();
    $session->set('ezproxy_return_url', $ezproxy_url);

    return new RedirectResponse('/ezproxy/auth');
  }

  public static function redirectEzproxy($pid, $ezproxy_url) {
    $ez_secret = \Drupal::config('ezproxy.settings')->get('ticket_secret');
    $ez_url = \Drupal::config('ezproxy.settings')->get('ezproxy_url');
    $ez_ticket = new EzproxyTicketController();
    $ez_ticket->EZproxyTicket($ez_url, $ez_secret, $pid, 'patron');
    $redirect_url = $ez_ticket->EZproxyStartingPointURL;

    return new RedirectResponse($redirect_url);
  }
}
