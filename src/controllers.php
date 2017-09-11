<?php

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Silex\Application;
use Payum\Core\PayumBuilder;
use Payum\Core\Payum;
use Payum\Core\Model\Payment;
use Payum\Core\Request\Capture;
use Payum\Core\Reply\HttpRedirect;
use Payum\Core\Request\GetHumanStatus;

//Request::setTrustedProxies(array('127.0.0.1'));

/**
 * @return Payum
 */
function getPayum()
{
    /** @var Payum $payum */
    return (new PayumBuilder())
        ->addDefaultStorages()
        ->addGateway(
            'aGateway',
            [
                'factory'   => 'paypal_express_checkout',
                'username'  => 't.jurczak-facilitator_api1.gmail.com',
                'password'  => '8X2CF9PG5K3NH7SS',
                'signature' => 'AFcWxV21C7fd0v3bYYYRCpSSRl31A34GPHs4v0YNoWZQOXG2ec3X4ij1',
                'sandbox'   => true,
            ]
        )
        ->setGenericTokenFactoryPaths(
            [
                'capture'   => 'capture',
                'notify'    => 'notify.php',
                'authorize' => 'authorize.php',
                'refund'    => 'refund.php',
                'payout'    => 'payout.php',
            ]
        )
        ->getPayum();
}

/** @var Application $app */

// main page
$app->get(
    '/',
    function () use ($app) {
        return $app['twig']->render('index.html.twig', []);
    }
)->bind('homepage');

$app->post('/pay', function (Request $request) use ($app) {
    $username = $request->get('username', null);
    $username = $app->escape($username);
    if (empty($username) || $username == null) {
        $app['session']->getFlashBag()->add('warning', '<b>Oops!</b> You forgot username!');
        return $app->redirect("/");
    }

    $paymentClass = Payment::class;

    /** @var Payum $payum */
    $payum = getPayum();

    $gatewayName = 'aGateway';

    /** @var \Payum\Core\Payum $payum */
    $storage = $payum->getStorage($paymentClass);

    /** @var Payment $payment */
    $payment = $storage->create();
    $payment->setNumber(uniqid());
    $payment->setCurrencyCode('EUR');
    $payment->setTotalAmount(100); // 1.00 EUR
    $payment->setDescription($username);

    $storage->update($payment);

    $captureToken = $payum->getTokenFactory()->createCaptureToken($gatewayName, $payment, 'done');

    return $app->redirect($captureToken->getTargetUrl());
})->bind('pay');

$app->get('/pay/capture', function () use ($app) {
        /** @var Payum $payum */
        $payum = getPayum();

        $token   = $payum->getHttpRequestVerifier()->verify($_REQUEST);
        $gateway = $payum->getGateway($token->getGatewayName());

        /** @var \Payum\Core\GatewayInterface $gateway */
        if ($reply = $gateway->execute(new Capture($token), true)) {
            if ($reply instanceof HttpRedirect) {
                return $app->redirect($reply->getUrl());
            }

            throw new \LogicException('Unsupported reply', null, $reply);
        }

        /** @var \Payum\Core\Payum $payum */
        $payum->getHttpRequestVerifier()->invalidate($token);

        return $app->redirect($token->getAfterUrl());
    }
)->bind('pay/capture');

$app->get(
    '/pay/done',
    function () use ($app) {

        /** @var Payum $payum */
        $payum = getPayum();

        /** @var \Payum\Core\Payum $payum */
        try {
            $token   = $payum->getHttpRequestVerifier()->verify($_REQUEST);
        } catch (\Exception $e) {
            return $app->redirect('/');
        }

        $gateway = $payum->getGateway($token->getGatewayName());

        $payum->getHttpRequestVerifier()->invalidate($token);

        // or Payum can fetch the model for you while executing a request (Preferred).
        $gateway->execute($status = new GetHumanStatus($token));
        /** @var Payment $payment */
        $payment = $status->getFirstModel();

        $username = $payment->getDescription();
        /** @var Connection $db */
        $db = $app['db'];

        $db->insert('users', ['username' => $app->escape($username), 'createdAt' => time()]);

        $myId = $db->fetchAssoc('SELECT id FROM users where username = ?', [$username]);
        $users = $db->fetchAll('SELECT * FROM users ORDER BY ID ASC');

        return $app['twig']->render('list.html.twig', ['myId' => $myId['id'], 'users' => $users]);
    }
)->bind('pay/done');

// error handler
$app->error(
    function (\Exception $e, Request $request, $code) use ($app) {
        if ($app['debug']) {
            return;
        }

        // 404.html, or 40x.html, or 4xx.html, or error.html
        $templates = [
            'errors/'.$code.'.html.twig',
            'errors/'.substr($code, 0, 2).'x.html.twig',
            'errors/'.substr($code, 0, 1).'xx.html.twig',
            'errors/default.html.twig',
        ];

        return new Response($app['twig']->resolveTemplate($templates)->render(['code' => $code]), $code);
    }
);
