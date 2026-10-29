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
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Render\Markup;

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
    \Drupal::service('page_cache_kill_switch')->trigger();

    $session = $request->getSession();
    $session->remove('ezproxy_url');
    $session->remove('ezproxy_auth_pid');
    $session->remove('ezproxy_auth_uid');
    $session->remove('ezproxy_auth_valid_pids');

    // Retrieve the 'Referer' header securely
    $referer = $request->headers->get('referer');
    $refer_test = $request->query->get('refer_test');
    $do_libby_path = false;
    if ($referer == "https://sentry.libbyapp.com/" || $referer == "https://mlc.overdrive.com/" || $refer_test == "libby") {
      $do_libby_path = true;
    }


    $uid = \Drupal::currentUser()->id();
    $ezproxy_url = $request->query->get('url');
    $user = User::load($uid);
     
    $session = $request->getSession();
    $session->set('ezproxy_url', $ezproxy_url);


    if ($do_libby_path) {
      $current_user = \Drupal::currentUser();
      if ($current_user->isAuthenticated()) {
        if ($current_user->hasPermission('access ezproxy content')) {
          $session->set('ezproxy_auth_uid', $current_user->id());
          return new RedirectResponse('/ezproxy/libby-select');
        }
      }
      return new RedirectResponse('/ezproxy/libby');
    } else if (\Drupal::currentUser()->isAuthenticated()) {//automatic for logged in users
        $current_uid = \Drupal::currentUser()->id();
        $current_user = User::load($current_uid);
        if ($current_user->hasPermission('access ezproxy content')) {
          return $this->redirectEzproxy( $pid = $current_user->get('field_patron_id')->value, $ezproxy_url);
        }
    }

    return new RedirectResponse('/ezproxy/login');
  }


  public function display_login_libby(){
    \Drupal::service('page_cache_kill_switch')->trigger();

    $html = '<div class="display"></div>';

    $credForm = $this->formBuilder()->getForm('Drupal\ezproxy\Form\EzproxyLibbyAuthByCred');
    $cardForm = $this->formBuilder()->getForm('Drupal\ezproxy\Form\EzproxyLibbyAuthByCard');
    $renderer = \Drupal::service('renderer');
    $credFormHtml = $renderer->render($credForm);
    $cardFormHtml = $renderer->render($cardForm);

        return [
            '#markup' => Markup::create("
                <div class='forms-display'>
                {$credFormHtml}
                <div class='form-divider'></div>
                {$cardFormHtml}
                </div>
            "),
            '#attached' => [
              'library' => [
                'ezproxy/libby', // Format: extension/library_name
              ],
            ],
        ];
    
  }

  public static function redirectEzproxy($pid, $ezproxy_url) {
    $ez_secret = \Drupal::config('ezproxy.settings')->get('ticket_secret');
    $ez_url = \Drupal::config('ezproxy.settings')->get('ezproxy_url');
    $ez_ticket = new EzproxyTicketController();
    $ez_ticket->EZproxyTicket($ez_url, $ez_secret, $pid, 'patron');
    $redirect_url = $ez_ticket->EZproxyStartingPointURL . "&url=$ezproxy_url";

    $response = new TrustedRedirectResponse($redirect_url);
    $response->addCacheableDependency((new \Drupal\Core\Cache\CacheableMetadata())->setCacheMaxAge(0));
    
    // Force the browser to never cache the redirect header
    $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
    
    return $response;
  }

}
