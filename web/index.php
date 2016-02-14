<?php
require('../vendor/autoload.php');

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

// Web handlers.
$app->get('/', function() use($app) {
  $app['monolog']->addDebug('logging output.');

  $client = new GuzzleHttp\Client();
  $res = $client->request('GET', 'http://fluxsauce.github.io/resume/');
  if ($res->getStatusCode() != 200) {

  }
  if ($res->getHeader('content-type')[0] !== '') {

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

  $wrapper = htmlspecialchars($qp->html());

  foreach (array_keys($tags) as $tag) {
    $comment_start = comment($tag, TRUE);
    $wrapper = str_replace(htmlspecialchars($comment_start), '<span class="tag_' . $tag . '">', $wrapper);
    $comment_end = comment($tag, FALSE);
    $wrapper = str_replace(htmlspecialchars($comment_end), '</span>', $wrapper);
  }

  // Sort by count.
  arsort($tags);
  $twig_tags = array();

  foreach ($tags as $tag => $count) {
    $twig_tags[] = array('name' => $tag, 'count' => $count);
  }

  return $app['twig']->render('index.twig', array(
    'source' => $wrapper,
    'tags' => $twig_tags,
  ));
});

$app->run();