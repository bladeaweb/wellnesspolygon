<?php

/**
 * @file
 * Template for an Image Zoom gallery.
 *
 * Available variables:
 * - $image: The display image.
 * - $thumbs: An array of thumbnail images.
 */
?>

<?php print $image; ?>

<div id="imagezoom-thumb-wrapper">
  <?php foreach ($thumbs as $thumb): ?>
    <?php print $thumb; ?>
  <?php endforeach; ?>
</div>
