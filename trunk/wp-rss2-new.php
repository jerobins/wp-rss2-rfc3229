<?php 
/* 
   Implementation of RFC3229 by James E. Robinson, III
   Version 0.9  03.04.2005
   http://www.robinsonhouse.com/rfc3229

   Thanks to: 
               Garrett Rooney - http://asdf.blogs.com/asdf/
               Sam Ruby - http://www.intertwingly.net/blog/
               Bob Wyman - http://bobwyman.pubsub.com/
               Anthony Yager - http://wil.buildtolearn.net/

   History:
      0.9 - removed custom enclosure support and added wp1.5 support
      0.8 - add support for RSS2 Enclosures via three new custom fields:
            enclosure - required - URL to file
            enclosure-file - required - full path to filename
            enclosure-type - optional - default: audio/mpeg
         
      0.7 - bug-fixes - Anthony Yager
      0.6 - bug-fixes
      0.5 - change 'diffe' to send full XML file in ed script format - Sam Ruby
      0.4 - bug fixes for 'feed' method
      0.3 - add basic support for 'feed' method, fix 'diffe' bugs
      0.2 - quick hack for 'diffe' mode of RFC3229 - Bob Wyman
      0.1 - quick hack to operate solely on if-modified-since header
*/
if (!isset($feed)) {
    $blog = 1;
    $doing_rss = 1;
    require('wp-blog-header.php');
}

// Get time of last post
$lastModified = strtotime(mysql2date('D, d M Y H:i:s +0000', 
                           get_lastpostmodified('GMT'), 0));

// Get HTTP request headers - Apache only
$request = apache_request_headers();

// Begin logic to implement HTTP Delta Encoding a la
// http://www.faqs.org/rfcs/rfc3229.html
$modifiedSince = 0;

if (isset($request['If-Modified-Since'])) {
   // Split the If-Modified-Since (Netscape < v6 gets this wrong)
   // Thanks SitePoint
   $modifiedSince = explode(';', $request['If-Modified-Since']);

   // Turn the client request If-Modified-Since into a timestamp
   $modifiedSince = strtotime($modifiedSince[0]);
}

// We posted anything since they last checked?
if ($lastModified <= $modifiedSince) {
   header('HTTP/1.1 304 Not Modified');
   exit();
}

function deltaMethod( $requestHdr, $type ) {
   return in_array($type, array($requestHdr));
}

if ( $modifiedSince && isset($request['A-IM']) ) {

   // order determine our preferred method
   $methods = array( 'feed', 'diffe' );

   foreach ( $methods as $method ) {
      if ( deltaMethod($request['A-IM'], $method) ) {
         // Send appropriate RFC3229 headers
         header('HTTP/1.1 226 IM Used');
         header('Cache-Control: no-store, im');
         header("IM: $method");
         header('Last-Modified: ' .
                  gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');

         $im[$method] = 1;
      }
   }
}

$more = 1;
$charset = get_settings('blog_charset');
if (!$charset) $charset = 'UTF-8';
header('Content-type: text/xml', true);

if ( isset($im['diffe']) ) {
   // begin of diffe format (diff -e)
   echo "1,14c\n";
}

echo '<?xml version="1.0" encoding="' . $charset . '"?'.'>';

?>

<!-- generator="wordpress/<?php echo $wp_version ?>" -->
<rss version="2.0" 
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wfw="http://wellformedweb.org/CommentAPI/"
>

<channel>
	<title><?php bloginfo_rss('name') ?></title>
	<link><?php bloginfo_rss('url') ?></link>
	<description><?php bloginfo_rss("description") ?></description>
	<copyright>Copyright <?php echo mysql2date('Y', get_lastpostdate()); ?></copyright>
	<pubDate><?php echo mysql2date('D, d M Y H:i:s +0000', get_lastpostmodified('GMT'), 0); ?></pubDate>
	<generator>http://wordpress.org/?v=<?php echo $wp_version ?></generator>

<?php 
if ( isset($im['diffe']) ) {
   echo "\n.\n";
   echo "15a\n";
   // Yeah, so?
   //
   // The above XML header is always 15 lines.  The diff will
   // only include new items, not changed.  Therefore, the logic
   // gets simplified greatly (80/20 rule ya know).
   // It also degrades nicely, if the modified-since time is
   // in the BC range (or epoch), then you get the default number
   // of entries configured for WordPress in a diffe format
}
   $items_count = 0; if ($posts) { foreach ($posts as $post) { 
      start_wp();
      if ( $modifiedSince && 
               ( isset($im['diffe']) || isset($im['feed']) ) ) {
         $postModified = strtotime(mysql2date('D, d M Y H:i:s +0000', 
                                              $post->post_date_gmt, 0));
         // if post is older, skip and check the next one
         if ($postModified < $modifiedSince) {
            continue;
         }
      }
      ?>
	<item>
		<title><?php the_title_rss() ?></title>
		<link><?php permalink_single_rss() ?></link>
		<comments><?php comments_link(); ?></comments>
		<pubDate><?php echo mysql2date('D, d M Y H:i:s +0000', $post->post_date_gmt, 0); ?></pubDate>
		<?php the_category_rss() ?>
		<guid><?php the_permalink($id); ?></guid>
<?php if (get_settings('rss_use_excerpt')) : ?>
		<description><?php the_excerpt_rss(get_settings('rss_excerpt_length'), 2) ?></description>
<?php else : ?>
		<description><?php the_excerpt_rss(get_settings('rss_excerpt_length'), 2) ?></description>
		<content:encoded><![CDATA[<?php the_content('', 0, '') ?>]]></content:encoded>
<?php endif; ?>
		<wfw:commentRSS><?php echo comments_rss(); ?></wfw:commentRSS>
      <?php rss_enclosure(); ?>
	</item>
	<?php 
      $items_count++;
      if (($items_count == get_settings('posts_per_rss')) && empty($m)) {
         break; 
      } 
   } }
   if ( isset($im['diffe']) ) {
      echo "\n.\n";
      // with diffe format, always send valid XML wrapped in valid diffe
      // commands - liberal XML parsers will actually ignore them
      echo "$\n-1\n;c\n";
   }
?>
</channel>
</rss>
<?php
if ( isset($im['diffe']) ) {
   // end of diffe format
   echo "\n.\n";
}
?>
