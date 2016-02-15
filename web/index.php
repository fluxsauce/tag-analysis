<?php
/**
 * @file
 * HTML Tag Analysis.
 */

require('../vendor/autoload.php');

use Silex\Application;
use Silex\Provider\FormServiceProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\Url;

/**
 * Generate HTML placeholders for replacement.
 *
 * @param string $tag
 *   The name of a HTML tag for unique replacement.
 * @param bool $start
 *   If TRUE, will render as the start of a tag.
 *
 * @return string
 *   HTML comment.
 */
function placeholderTag($tag, $start = TRUE) {
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
  // Create form.
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

  // Handle form submission.
  if ($request->isMethod('POST')) {
    $form->handleRequest($request);
  }
  // Use defaults if not submitted.
  else {
    $form->bind($data);
  }

  // Prepare defaults for display.
  $twig_variables = array(
    'source' => '',
    'tags' => array(),
    'form' => $form->createView(),
    'errors' => NULL,
  );

  // If form is valid...
  if ($form->isValid()) {
    $data = $form->getData();

    // Log the request.
    $app['monolog']->addDebug($data['url']);

    // Get the destination URL.
    $client = new GuzzleHttp\Client();
    $response = NULL;
    try {
      $response = $client->request('GET', $data['url']);
    }
    catch (GuzzleHttp\Exception\ConnectException $e) {
      $twig_variables['errors'] = $e->getMessage();
    }
    catch (GuzzleHttp\Exception\ClientException $e) {
      $twig_variables['errors'] = $e->getMessage();
    }

    // Validate response.
    if ($response) {
      // Validate status.
      if ($response->getStatusCode() != 200) {
        $twig_variables['errors'] = 'Unable to retrieve the target URL.';
      }
      // Validate response.
      elseif (substr($response->getHeader('content-type')[0], 0, 9) !== 'text/html') {
        $twig_variables['errors'] = 'Target is not HTML; cannot parse.';
      }
    }

    if (!$twig_variables['errors']) {
      // Load the document into parser.
      $qp = @qp((string) $response->getBody(), NULL, array(
        'ignore_parser_warnings' => TRUE,
      ));

      // Prepare to count tags.
      $tags = array();

      // For all elements.
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
        $query->before(placeholderTag($tag, TRUE));
        $query->after(placeholderTag($tag, FALSE));
      }

      // Escape the entire source.
      $source = htmlspecialchars($qp->html());

      // Cleanup.
      unset($qp, $client, $res, $html5);

      // Replace placeholders with actual HTML.
      foreach (array_keys($tags) as $tag) {
        // Start of tags.
        $placeholder_start = placeholderTag($tag, TRUE);
        $source = str_replace(htmlspecialchars($placeholder_start), '<span class="tag_' . $tag . '">', $source);
        // End of tags.
        $placeholder_end = placeholderTag($tag, FALSE);
        $source = str_replace(htmlspecialchars($placeholder_end), '</span>', $source);
      }

      // Set the final result.
      $twig_variables['source'] = $source;

      // Sort by count.
      arsort($tags);

      // Flatten tags for easy Twig traversal.
      foreach ($tags as $tag => $count) {
        $twig_variables['tags'][] = array('name' => $tag, 'count' => $count);
      }
    }
  }

  // Render.
  return $app['twig']->render('index.twig', $twig_variables);
});

$app->run();