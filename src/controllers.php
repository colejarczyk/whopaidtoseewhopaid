<?php

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
        ->addGateway('aGateway', [
            'factory' => 'paypal_express_checkout',
            'username'  => 't.jurczak-facilitator_api1.gmail.com',
            'password'  => '8X2CF9PG5K3NH7SS',
            'signature' => 'AFcWxV21C7fd0v3bYYYRCpSSRl31A34GPHs4v0YNoWZQOXG2ec3X4ij1',
            'sandbox'   => true,
        ])
        ->setGenericTokenFactoryPaths([
            'capture' => 'capture',
            'notify' => 'notify.php',
            'authorize' => 'authorize.php',
            'refund' => 'refund.php',
            'payout' => 'payout.php',
        ])
        ->getPayum()
    ;
}

/** @var Application $app */

// main page
$app->get('/', function () use ($app) {
    return $app['twig']->render('index.html.twig', []);
})->bind('homepage');

$app->get('/pay', function () use ($app) {
    $paymentClass = Payment::class;

    /** @var Payum $payum */
    $payum = getPayum();

    $gatewayName = 'aGateway';

    /** @var \Payum\Core\Payum $payum */
    $storage = $payum->getStorage($paymentClass);

    $payment = $storage->create();
    $payment->setNumber(uniqid());
    $payment->setCurrencyCode('EUR');
    $payment->setTotalAmount(123); // 1.23 EUR
    $payment->setDescription('A description');
    $payment->setClientId('anId');
    $payment->setClientEmail('foo@example.com');

    $payment->setDetails(array(
        // put here any fields in a gateway format.
        // for example if you use Paypal ExpressCheckout you can define a description of the first item:
        // 'L_PAYMENTREQUEST_0_DESC0' => 'A desc',
    ));

    $storage->update($payment);

    $captureToken = $payum->getTokenFactory()->createCaptureToken($gatewayName, $payment, 'done');

    header("Location: ".$captureToken->getTargetUrl());
    die();

})->bind('pay');

$app->get('/pay/capture', function () use ($app) {

    $paymentClass = Payment::class;

    /** @var Payum $payum */
    $payum = getPayum();

    $token = $payum->getHttpRequestVerifier()->verify($_REQUEST);
    $gateway = $payum->getGateway($token->getGatewayName());

    /** @var \Payum\Core\GatewayInterface $gateway */
    if ($reply = $gateway->execute(new Capture($token), true)) {
        if ($reply instanceof HttpRedirect) {
            header("Location: ".$reply->getUrl());
            die();
        }

        throw new \LogicException('Unsupported reply', null, $reply);
    }

    /** @var \Payum\Core\Payum $payum */
    $payum->getHttpRequestVerifier()->invalidate($token);

    header("Location: ".$token->getAfterUrl());
    die();

})->bind('pay/capture');

$app->get('/pay/done', function () use ($app) {

    /** @var Payum $payum */
    $payum = getPayum();

    /** @var \Payum\Core\Payum $payum */
    $token = $payum->getHttpRequestVerifier()->verify($_REQUEST);
    $gateway = $payum->getGateway($token->getGatewayName());

// you can invalidate the token. The url could not be requested any more.
// $payum->getHttpRequestVerifier()->invalidate($token);

// Once you have token you can get the model from the storage directly.
//$identity = $token->getDetails();
//$payment = $payum->getStorage($identity->getClass())->find($identity);

// or Payum can fetch the model for you while executing a request (Preferred).
    $gateway->execute($status = new GetHumanStatus($token));
    $payment = $status->getFirstModel();

    header('Content-Type: application/json');
    $data = json_encode([
        'status' => $status->getValue(),
        'order' => [
            'total_amount' => $payment->getTotalAmount(),
            'currency_code' => $payment->getCurrencyCode(),
            'details' => $payment->getDetails(),
        ],
    ]);

    return $app['twig']->render('list.html.twig', ['data' => $data]);

})->bind('pay/done');



// error handler
$app->error(function (\Exception $e, Request $request, $code) use ($app) {
    if ($app['debug']) {
        return;
    }

    // 404.html, or 40x.html, or 4xx.html, or error.html
    $templates = array(
        'errors/'.$code.'.html.twig',
        'errors/'.substr($code, 0, 2).'x.html.twig',
        'errors/'.substr($code, 0, 1).'xx.html.twig',
        'errors/default.html.twig',
    );

    return new Response($app['twig']->resolveTemplate($templates)->render(array('code' => $code)), $code);
});
