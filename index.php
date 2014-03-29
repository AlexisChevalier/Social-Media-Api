<?php
/**
 * Main application - Processing the api calls and displays the results
 */

/**
 * Configuration file for API keys
 */
require_once('config.php');

/**
 * Composer autoloader for modules
 */
require_once('vendor/autoload.php');


/**
 * FB Init
 */
$facebook = new Facebook(array(
    'appId'  => FACEBOOK_ID,
    'secret' => FACEBOOK_KEY
));

/**
 * Try to get the facebook user
 */
$user = $facebook->getUser();

/**
 * Sorting arrays
 */
$movies = array();
$best_film = array();

$checkins = array();
$best_checkin = array();


/**
 * If the user has authorized the application and is connected to facebook
 */
if ($user) {
    /**
     * FACEBOOK PART
     * Library : https://github.com/facebook/facebook-php-sdk (Loaded with Composer)
     */

    /**
     * Get friends list
     */
    $user_friends = $facebook->api('/me/friends');
    foreach($user_friends['data'] as $friend) {
        $userid = $friend['id'];

        /**
         * Get movies liked by actual friend
         */
        $friends_movies = $facebook->api("/$userid/movies");
        foreach($friends_movies['data'] as $movie) {
            if ($movie['category'] == "Movie") {
                if(isset($movies[$movie['id']])) {
                    $movies[$movie['id']]["nb"]++;
                } else {
                    $movies[$movie['id']] = array("nb" => 1, "name" => $movie["name"]);
                }
            }
        }

        /**
         * Same for checkins (unused for now)
         */
        /*$friends_checkins = $facebook->api("/$userid/checkins");
        foreach($friends_checkins['data'] as $checkin) {
            if(isset($checkins[$checkin["place"]['id']])) {
                $checkins[$checkin["place"]["id"]]["nb"]++;
            } else {
                $checkins[$checkin["place"]["id"]] = array("nb" => 1, "name" => $checkin["place"]['name']);
            }
        }*/
    }

    /**
     * Sort the movies
     */
    function cmp_items($a, $b) {
        return $b["nb"] - $a["nb"];
    }

    usort($movies, "cmp_items");
    reset($movies);
    list($movieKey, $firstMovie) = each($movies);
    $best_film['name'] = $firstMovie['name'];
    $best_film['fbId'] = $movieKey;
    $best_film['likeCount'] = $firstMovie['nb'];

    /**
     * Same for checkins (unused for now)
     */
    /*usort($checkins, "cmp_items");
    reset($checkins);
    list($checkinKey, $firstCheckin) = each($checkins);
    $best_checkin['name'] = $firstCheckin['name'];
    $best_checkin['fbId'] = $checkinKey;
    $best_checkin['likeCount'] = $firstCheckin['nb'];
    */

    /**
     * IMDB PART
     * Library : There is no library used for this API
     */

    /**
     * Get and fetches favorite movie's details
     */
    $IMDBdetails = json_decode(file_get_contents("http://www.omdbapi.com/?t=" . urlencode($best_film['name'])));
    $image_data=file_get_contents($IMDBdetails->Poster);

    /**
     * Here we are encoding the poster using the base64 encoding because IMDB disallows the integration of it's pictures from
     * another website, and i don't want to store them on my filesystem
     */
    $IMDBdetails->Poster=base64_encode($image_data);
    $best_film['imdb'] = $IMDBdetails;

    /**
     * YOUTUBE PART
     * Library : https://github.com/google/google-api-php-client (Loaded with Composer)
     */

    /**
     * Google & YT init
     */
    $googleClient = new Google_Client();
    $googleClient->setDeveloperKey(GOOGLE_KEY);
    $youtube = new Google_Service_YouTube($googleClient);

    /**
     * Search on youtube for the trailer
     */
    $searchResponse = $youtube->search->listSearch('id,snippet', array(
        'q' => $best_film['name'] . " trailer official",
        'maxResults' => 1,
        'type' => 'video'
    ));

    /**
     * Fetch the trailer
     */
    $trailers = $searchResponse->getItems();
    $best_film["youtubeId"] = $trailers[0]["id"]["videoId"];

    /**
     * TWITTER PART
     * Library : https://github.com/J7mbo/twitter-api-php (Loaded with Composer)
     */

    /**
     * Twitter init
     */
    $twitter_settings = array(
        'oauth_access_token' => TWITTER_OAUTH_TOKEN,
        'oauth_access_token_secret' => TWITTER_OAUTH_TOKEN_SECRET,
        'consumer_key' => TWITTER_KEY,
        'consumer_secret' => TWITTER_SECRET
    );

    /**
     * Preparing the search request
     */
    $url = 'https://api.twitter.com/1.1/search/tweets.json';
    $getfield = '?q=' . urlencode($best_film['name']) . '&result_type=recent&count=10';
    $requestMethod = 'GET';

    /**
     * Process to application-only authentication and request
     */
    $twitter = new TwitterAPIExchange($twitter_settings);
    $tweets = json_decode($twitter->setGetfield($getfield)
        ->buildOauth($url, $requestMethod)
        ->performRequest());

    /**
     * Fetching tweets
     */
    $best_film['tweets'] = array();
    foreach($tweets->statuses as $tweet) {
        $tmp_tweet = array(
            "user_name" => $tweet->user->name,
            "user_screen_name" => $tweet->user->screen_name,
            "text" => $tweet->text
        );
        array_push($best_film['tweets'], $tmp_tweet);
    }

} else {
    /**
     * If the user has not authorized the app or isn't logged to facebook, we display the login url
     */
    $loginUrl = $facebook->getLoginUrl(array(
        'scope' => 'user_friends, friends_checkins, friends_likes',
    ));
    $loginUrl = "<a href='$loginUrl'>Login to facebook</a>";
}



?>

<!DOCTYPE html>
<!--[if lt IE 7]>      <html class="no-js lt-ie9 lt-ie8 lt-ie7"> <![endif]-->
<!--[if IE 7]>         <html class="no-js lt-ie9 lt-ie8"> <![endif]-->
<!--[if IE 8]>         <html class="no-js lt-ie9"> <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-js"> <!--<![endif]-->
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <title>Social Media APIs</title>
    <meta name="description" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="css/bootstrap.min.css">
    <style>
        body {
            padding-top: 50px;
            padding-bottom: 20px;
        }
    </style>
    <link rel="stylesheet" href="css/bootstrap-theme.min.css">
    <link rel="stylesheet" href="css/main.css">

    <script src="js/vendor/modernizr-2.6.2-respond-1.1.0.min.js"></script>
</head>
<body>
<div class="navbar navbar-inverse navbar-fixed-top" role="navigation">
    <div class="container">
        <div class="navbar-header">
            <p class="navbar-brand">Social Media APIs (by <a href="https://twitter.com/Heavenstar_">@Heavenstar_</a>)</p>
        </div>
    </div>
</div>

<?php
/**
 * If there is a loginUrl defined then we displays it and that's all
 */
if(isset($loginUrl) && !empty($loginUrl)) {
?>
<div class="row">
    <div class="col-md-12">
        <?php echo $loginUrl ?>
    </div>
</div>
<?php
    /**
     * If there is no loginUrl, we displays the most liked movie and the statistics
     */
    } else {
?>

<div class="jumbotron">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <h2>Your friends's most liked movie is...</h2>
                <h1><?php

                    /**
                     * We displays the name of the movie and the number of likes
                     */
                    echo $best_film['name'] . " (" . $best_film['likeCount'] . " likes)";

                    ?></h1>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <?php
                /**
                 * Here we are displaying the movie details from IMDB
                 */
                ?>
                <h2>Details</h2>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <colgroup>
                            <col class="col-xs-1">
                            <col class="col-xs-7">
                        </colgroup>
                        <tbody>
                        <tr>
                            <td>
                                Plot
                            </td>
                            <td><?php echo $best_film['imdb']->Plot; ?></td>
                        </tr>
                        <tr>
                            <td>
                                Released
                            </td>
                            <td><?php echo $best_film['imdb']->Released; ?></td>
                        </tr>
                        <tr>
                            <td>
                                Genre
                            </td>
                            <td><?php echo $best_film['imdb']->Genre; ?></td>
                        </tr>
                        <tr>
                            <td>
                                Actors
                            </td>
                            <td><?php echo $best_film['imdb']->Actors; ?></td>
                        </tr>
                        <tr>
                            <td>
                                Poster
                            </td>
                            <td><?php

                                /**
                                 * Here we are displaying the poster using the base64 encoding (Check the api's data recuperation php code for the reasons)
                                 */

                                echo '<img src="data:image/png;base64,' . $best_film['imdb']->Poster . '" alt="' . $best_film['name'] . '"/>';

                                ?></td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-md-6">
                <h2>Video</h2>
                <?php

                /**
                 * Customizing youtube integration code with the id we got from the API
                 */

                echo "<object width=\"560\" height=\"315\"><param name=\"movie\" value=\"//www.youtube.com/v/" . $best_film["youtubeId"] . "?hl=fr_FR&amp;version=3&amp;rel=0\"></param><param name=\"allowFullScreen\" value=\"true\"></param><param name=\"allowscriptaccess\" value=\"always\"></param><embed src=\"//www.youtube.com/v/" . $best_film["youtubeId"] . "?hl=fr_FR&amp;version=3&amp;rel=0\" type=\"application/x-shockwave-flash\" width=\"560\" height=\"315\" allowscriptaccess=\"always\" allowfullscreen=\"true\"></embed></object>"

                ?>

                <h2>Tweets</h2>
                <ul class="list-group">
                    <?php
                    /**
                     * Displaying the tweets
                     */
                    foreach($best_film["tweets"] as $tweet) {
                        echo "<li class=\"list-group-item\"><strong><a href='https://twitter.com/" . $tweet['user_screen_name'] . "'> " . $tweet['user_name'] . "</a> </strong> - " . $tweet['text'] . "</li>";
                    }
                    ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <!-- Example row of columns -->
    <div class="row">
        <div class="col-md-12">
            <h2>Your friends also like...</h2>
            <table class="table table-bordered table-striped">
                <colgroup>
                    <col class="col-xs-7">
                    <col class="col-xs-1">
                </colgroup>
                <tbody>
                <?php
                /**
                 * Now i'm looping over the other movies to display them with their likes count
                 */

                $keys = array_keys($movies);
                    for ($keyindex = 1; $keyindex < count($keys); $keyindex++) {
                        $key = $keys[$keyindex];
                        $val = $movies[$key];
                ?>

                <tr>
                    <td><?php echo $val['name']; ?></td>
                    <td><?php echo $val['nb'] . " likes"; ?></td>
                </tr>

                <?php
                    }
                ?>

                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
    }
?>
<div class="container">
    <hr>
    <footer>
        <p>&copy; @Heavenstar_ <?php echo date('Y'); ?></p>
    </footer>
</div> <!-- /container -->
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
<script>window.jQuery || document.write('<script src="js/vendor/jquery-1.11.0.min.js"><\/script>')</script>

<script src="js/vendor/bootstrap.min.js"></script>
</body>
</html>
