<?php
require('../vendor/autoload.php');

use Silex\Provider\FormServiceProvider;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\Url;

function comment($tag, $start = TRUE) {
  return '<!-- ' . ($start ? 'START' : 'END') . md5($tag) . ' -->';
}

$app = new Silex\Application();
$app['debug'] = TRUE;

// Register the monolog logging service.
$app->register(new Silex\Provider\MonologServiceProvider(), array(
  'monolog.logfile' => 'php://stderr',
));

// Register view rendering.
$app->register(new Silex\Provider\TwigServiceProvider(), array(
  'twig.path' => __DIR__ . '/views',
));

// Register the form.
$app->register(new FormServiceProvider());

// Register form message rendering.
$app->register(new Silex\Provider\TranslationServiceProvider(), array(
  'translator.messages' => array(),
));

// Register form validation.
$app->register(new Silex\Provider\ValidatorServiceProvider());

// Web handler.
$app->match('/', function (Request $request) use ($app) {
  $data = array(
    'url' => 'http://fluxsauce.github.io/resume/',
  );
  $form = $app['form.factory']->createBuilder('form', $data, array('csrf_protection' => FALSE))
    ->add('url', 'url', array(
      'constraints' => array(
        new Url(),
      ),
      'attr' => array(
        'size' => '50',
      ),
    ))
    ->getForm();

  if ($request->isMethod('POST')) {
    $form->handleRequest($request);
  }
  else {
    $form->bind($data);
  }

  // Prepare to display.
  $twig_variables = array(
    'source' => '',
    'tags' => array(),
  );

  if ($form->isValid()) {
    $data = $form->getData();

    $app['monolog']->addDebug($data['url']);

    $client = new GuzzleHttp\Client();
    $res = $client->request('GET', $data['url']);

    // Validate status.
    if ($res->getStatusCode() != 200) {

    }

    // Validate response.
    if (substr($res->getHeader('content-type')[0], 0, 9) !== 'text/html') {

    }

    $html5 = new Masterminds\HTML5();
    $qp = qp($html5->loadHTML($res->getBody()));

    $tags = array();

    foreach ($qp->find('*') as $query) {
      // Determine tag name.
      $tag = $query->tag();

      // Initialize if it's never been seen before.
      if (!isset($tags[$tag])) {
        $tags[$tag] = 0;
      }
      // Count the tag.
      $tags[$tag]++;

      // Surround the tag.
      $query->before(comment($tag, TRUE));
      $query->after(comment($tag, FALSE));
    }

    $source = htmlspecialchars($qp->html());

    foreach (array_keys($tags) as $tag) {
      $comment_start = comment($tag, TRUE);
      $source = str_replace(htmlspecialchars($comment_start), '<span class="tag_' . $tag . '">', $source);
      $comment_end = comment($tag, FALSE);
      $source = str_replace(htmlspecialchars($comment_end), '</span>', $source);
    }

    $twig_variables['source'] = $source;

    // Sort by count.
    arsort($tags);

    foreach ($tags as $tag => $count) {
      $twig_variables['tags'][] = array('name' => $tag, 'count' => $count);
    }
  }

  $twig_variables['form'] = $form->createView();

  return $app['twig']->render('index.twig', $twig_variables);
});

$app->run();