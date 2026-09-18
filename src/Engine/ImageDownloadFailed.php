<?php

namespace Shazzoo\ContentStudio\Engine;

use RuntimeException;

/** Thrown while storing an article when one of its images cannot be fetched. */
class ImageDownloadFailed extends RuntimeException {}
