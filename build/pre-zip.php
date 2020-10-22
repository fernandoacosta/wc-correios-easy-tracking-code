<?php
echo "Publish to WP.org? (Y/n) ";
if ( 'Y' === trim( strtoupper( fgets( STDIN ) ) ) ) {
  if ( file_exists( __DIR__ . '/wp-org.php' ) ) {
    include __DIR__ . '/wp-org.php';
  }

  if ( ! isset( $username, $password ) ) {
    echo "Ignorando Deploy WP.org porque usuário e senha não foram definidos. \n";
    return;
  }

  echo `svn co -q https://plugins.svn.wordpress.org/wc-correios-easy-tracking-code svn`;
  echo `rm -R svn/trunk`;
  echo `mkdir svn/trunk`;
  echo `mkdir svn/tags/$version`;
  echo `rsync -r $plugin_slug/* svn/trunk/`;
  echo `rsync -r $plugin_slug/* svn/tags/$version`;
  echo `svn stat svn/ | grep '^\?' | awk '{print $2}' | xargs -I x svn add x@`;
  echo `svn stat svn/ | grep '^\!' | awk '{print $2}' | xargs -I x svn rm --force x@`;
  echo `svn stat svn/`;

  echo "Commit to WP.org? (Y/n)? ";
  if ( 'Y' === trim( strtoupper( fgets( STDIN ) ) ) ) {
    echo `svn ci svn/ -m "Deploy version $version" --username $username --password $password
    `;
  }
}
