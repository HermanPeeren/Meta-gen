<?php

/**
 * The Joomla constants this component reads.
 *
 * Joomla defines these in `includes/defines.php` when an application boots, so
 * they exist at runtime but not in any file an analyser reads. Declaring them
 * here is the whole of the fix: without it every use is reported, which hides
 * the findings that mean something.
 *
 * Only the ones this component actually reads are here. A bootstrap that
 * defines everything is one nobody can read to answer "what does this depend
 * on".
 *
 * A bootstrap rather than a stub file: PHPStan's stubs describe classes and
 * functions, and a constant has to be defined by something that runs.
 */

declare(strict_types=1);

// Where components are installed, and so where a target's published vocabulary
// descriptor is found. See VocabularyContext.
\define('JPATH_ADMINISTRATOR', '');

// The site root, which the generate task strips off a path before showing it to
// somebody - an absolute path on the server is not an answer to "where did it
// go".
\define('JPATH_ROOT', '');

// The libraries directory, where the shared Yepr Gen library is installed and
// where a site's composer autoloader lives. The tools that boot an installed
// Joomla read it.
\define('JPATH_LIBRARIES', '');
