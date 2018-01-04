<?php

// This PHP class can be used to generate EZproxy tickets.  See
// ticket URLs.  When creating a new EZproxyTicket option, 
// the first parameter is the EZproxy server base
// URL, the second is the shared secret that appears in user.txt/ezproxy.usr,
// the third is a username to associate with the user, and the
// last is optional and specifies any EZproxy groups the user 
// should be associated with.
//
// If you use a shared secret of shhh, then in user.txt/ezproxy.usr you
// might use:
//      ::Ticket
//      MD5 shhhh
//      /Ticket
// to allow EZproxy to recognzie your tickets.
//
// Once the object is created, you can call its url method with a
// database URL to generate a ticket URL.

class EZproxyTicket {
  var $EZproxyStartingPointURL;

  function EZproxyTicket(
    $EZproxyServerURL,
    $secret,
    $user,
    $groups = "")
  {
    if (strcmp($secret, "") == 0) {
      echo("EZproxyURLInit secret cannot be blank");
      exit(1);
    }

    $packet = '$u' . time();
    if (strcmp($groups, "") != 0) {
      $packet .=  '$g' . $groups;
    }
    $EZproxyTicket = urlencode(md5($secret . $user . $packet) . $packet);
    $this->EZproxyStartingPointURL = $EZproxyServerURL . "/login?user=" . 
      urlencode($user) . "&ticket=" . $EZproxyTicket;
  }

  function URL($url)
  {
    return $this->EZproxyStartingPointURL . "&url=" . $url;
  }
}
?>
