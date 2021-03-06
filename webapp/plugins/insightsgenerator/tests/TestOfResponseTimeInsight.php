<?php
/**
 *
 * ThinkUp/webapp/plugins/insightsgenerator/tests/TestOfResponseTimeInsight.php
 *
 * Copyright (c) 2013-2014 Nilaksh Das, Gina Trapani
 *
 * LICENSE:
 *
 * This file is part of ThinkUp (http://thinkup.com).
 *
 * ThinkUp is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either version 2 of the License, or (at your option) any
 * later version.
 *
 * ThinkUp is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with ThinkUp.  If not, see
 * <http://www.gnu.org/licenses/>.
 *
 * Test of ResponseTimeInsight
 *
 * Test for the ResponseTimeInsight class.
 *
 * @license http://www.gnu.org/licenses/gpl.html
 * @copyright 2013-2014 Nilaksh Das, Gina Trapani
 * @author Nilaksh Das <nilakshdas [at] gmail [dot] com>
 */

require_once dirname(__FILE__) . '/../../../../tests/init.tests.php';
require_once THINKUP_WEBAPP_PATH.'_lib/extlib/simpletest/autorun.php';
require_once THINKUP_WEBAPP_PATH.'_lib/extlib/simpletest/web_tester.php';
require_once THINKUP_ROOT_PATH. 'webapp/plugins/insightsgenerator/model/class.InsightPluginParent.php';
require_once THINKUP_ROOT_PATH. 'webapp/plugins/insightsgenerator/insights/responsetime.php';

class TestOfResponseTimeInsight extends ThinkUpInsightUnitTestCase {

    public function setUp(){
        parent::setUp();
    }

    public function tearDown() {
        parent::tearDown();
    }

    public function testResponseTimeInsightForTwitterNoPriorBaseline() {
        // Get data ready that insight requires
        $instance = new Instance();
        $instance->id = 10;
        $instance->network_username = 'testeriffic';
        $instance->network = 'twitter';

        $posts = array();
        $posts[] = new Post(array(
            'reply_count_cache' => 5,
            'retweet_count_cache' => 15,
            'favlike_count_cache' => 3
        ));
        $posts[] = new Post(array(
            'reply_count_cache' => 0,
            'retweet_count_cache' => 5,
            'favlike_count_cache' => 15
        ));
        $posts[] = new Post(array(
            'reply_count_cache' => 2,
            'retweet_count_cache' => 5,
            'favlike_count_cache' => 1
        ));

        // Calculate time for each new retweet
        $time_per_response = InsightTerms::getSyntacticTimeDifference(floor((60 * 60 * 24 * 7) / 25));

        $insight_plugin = new ResponseTimeInsight();
        $insight_plugin->generateInsight($instance, null, $posts, 3);

        // Assert that insight got inserted
        $insight_dao = new InsightMySQLDAO();
        $today = date ('Y-m-d');
        $result = $insight_dao->getInsight('response_time', 10, $today);
        $this->debug(Utils::varDumpToString($result));
        $this->assertNotNull($result);
        $this->assertIsA($result, "Insight");
        $this->assertPattern('/\@testeriffic\'s tweets averaged <strong>1 new retweet/', $result->headline);
        $this->assertPattern('/every <strong>'.$time_per_response.'<\/strong>./', $result->headline);

        // Assert that baselines got inserted
        $insight_baseline_dao = new InsightBaselineMySQLDAO();
        $result_1 = $insight_baseline_dao->getInsightBaseline('response_count_reply', 10);
        $result_2 = $insight_baseline_dao->getInsightBaseline('response_count_retweet', 10);
        $result_3 = $insight_baseline_dao->getInsightBaseline('response_count_like', 10);
        $this->assertNotNull($result_1);
        $this->assertNotNull($result_2);
        $this->assertNotNull($result_3);
        $this->assertIsA($result_1, "InsightBaseline");
        $this->assertIsA($result_2, "InsightBaseline");
        $this->assertIsA($result_3, "InsightBaseline");
        $this->assertEqual($result_1->value, 7);
        $this->assertEqual($result_2->value, 25);
        $this->assertEqual($result_3->value, 19);
        $this->debug($this->getRenderedInsightInHTML($result));
        $this->debug($this->getRenderedInsightInEmail($result));
    }

    public function testResponseTimeInsightForFacebookPriorGreaterBaseline() {
        // Get data ready that insight requires
        $instance = new Instance();
        $instance->id = 10;
        $instance->network_username = 'testeriffic';
        $instance->network = 'facebook';

        $posts = array();
        $posts[] = new Post(array(
            'reply_count_cache' => 5,
            'retweet_count_cache' => 1,
            'favlike_count_cache' => 3
        ));
        $posts[] = new Post(array(
            'reply_count_cache' => 0,
            'retweet_count_cache' => 5,
            'favlike_count_cache' => 15
        ));
        $posts[] = new Post(array(
            'reply_count_cache' => 2,
            'retweet_count_cache' => 5,
            'favlike_count_cache' => 1
        ));

        // Add a baseline from prior week
        $last_week = date('Y-m-d', strtotime('-7 day'));
        $builder = FixtureBuilder::build('insight_baselines', array('date'=>$last_week, 'slug'=>'response_count_like',
        'instance_id'=>10, 'value'=>27));

        // Calculate time for each new favorite
        $time_per_response = InsightTerms::getSyntacticTimeDifference(floor((60 * 60 * 24 * 7) / 19));
        $last_week_time_per_response = InsightTerms::getSyntacticTimeDifference(floor((60 * 60 * 24 * 7) / 27));

        $insight_plugin = new ResponseTimeInsight();
        $insight_plugin->generateInsight($instance, null, $posts, 3);

        // Assert that insight got inserted
        $insight_dao = new InsightMySQLDAO();
        $today = date ('Y-m-d');
        $result = $insight_dao->getInsight('response_time', 10, $today);
        $this->debug(Utils::varDumpToString($result));
        $this->assertNotNull($result);
        $this->assertIsA($result, "Insight");
        $this->assertPattern('/testeriffic\'s status updates averaged <strong>1 new like/', $result->headline);
        $this->assertPattern('/every <strong>'.$time_per_response.'<\/strong>/', $result->headline);
        $this->assertPattern('/That\'s slower than the previous week\'s average/', $result->text);
        $this->assertPattern('/of 1 like every '.$last_week_time_per_response.'./', $result->text);
        $this->debug($this->getRenderedInsightInHTML($result));
        $this->debug($this->getRenderedInsightInEmail($result));
    }

    public function testResponseTimeInsightForFoursquarePriorSmallerBaseline() {
        // Get data ready that insight requires
        $instance = new Instance();
        $instance->id = 10;
        $instance->network_username = 'testeriffic';
        $instance->network = 'foursquare';

        $posts = array();
        $posts[] = new Post(array(
            'reply_count_cache' => 13,
            'retweet_count_cache' => 1,
            'favlike_count_cache' => 3
        ));
        $posts[] = new Post(array(
            'reply_count_cache' => 2,
            'retweet_count_cache' => 5,
            'favlike_count_cache' => 7
        ));
        $posts[] = new Post(array(
            'reply_count_cache' => 2,
            'retweet_count_cache' => 5,
            'favlike_count_cache' => 1
        ));

        // Add a baseline from prior week
        $last_week = date('Y-m-d', strtotime('-7 day'));
        $builder = FixtureBuilder::build('insight_baselines', array('date'=>$last_week, 'slug'=>'response_count_reply',
        'instance_id'=>10, 'value'=>12));

        // Calculate time for each new favorite
        $time_per_response = InsightTerms::getSyntacticTimeDifference(floor((60 * 60 * 24 * 7) / 17));
        $last_week_time_per_response = InsightTerms::getSyntacticTimeDifference(floor((60 * 60 * 24 * 7) / 12));

        $insight_plugin = new ResponseTimeInsight();
        $insight_plugin->generateInsight($instance, null, $posts, 3);

        // Assert that insight got inserted
        $insight_dao = new InsightMySQLDAO();
        $today = date ('Y-m-d');
        $result = $insight_dao->getInsight('response_time', 10, $today);
        $this->debug(Utils::varDumpToString($result));
        $this->assertNotNull($result);
        $this->assertIsA($result, "Insight");
        $this->assertPattern('/testeriffic\'s checkins averaged <strong>1 new comment/', $result->headline);
        $this->assertPattern('/every <strong>'.$time_per_response.'<\/strong>/', $result->headline);
        $this->assertPattern('/That\'s faster than the previous week\'s average/', $result->text);
        $this->assertPattern('/of 1 comment every '.$last_week_time_per_response.'./', $result->text);
        $this->debug($this->getRenderedInsightInHTML($result));
        $this->debug($this->getRenderedInsightInEmail($result));
    }

    public function testResponseTimeInsightWithUnitTimeValue() {
        // Get data ready that insight requires
        $instance = new Instance();
        $instance->id = 10;
        $instance->network_username = 'testeriffic';
        $instance->network = 'twitter';

        $posts = array();
        $posts[] = new Post(array(
            'reply_count_cache' => 0,
            'retweet_count_cache' => 0,
            'favlike_count_cache' => (24 * 7)
        ));

        // Add a baseline from prior week
        $last_week = date('Y-m-d', strtotime('-7 day'));
        $builder = FixtureBuilder::build('insight_baselines', array('date'=>$last_week, 'slug'=>'response_count_like',
        'instance_id'=>10, 'value'=>7));

        $insight_plugin = new ResponseTimeInsight();
        $insight_plugin->generateInsight($instance, null, $posts, 3);

        // Assert that insight got inserted
        $insight_dao = new InsightMySQLDAO();
        $today = date ('Y-m-d');
        $result = $insight_dao->getInsight('response_time', 10, $today);
        $this->debug(Utils::varDumpToString($result));
        $this->assertNotNull($result);
        $this->assertIsA($result, "Insight");
        $this->assertPattern('/\@testeriffic\'s tweets averaged <strong>1 new favorite/', $result->headline);
        $this->assertPattern('/every <strong>hour<\/strong>/', $result->headline);
        $this->assertPattern('/That\'s faster than the previous week\'s average/', $result->text);
        $this->assertPattern('/of 1 favorite every day./', $result->text);
        $this->debug($this->getRenderedInsightInHTML($result));
        $this->debug($this->getRenderedInsightInEmail($result));
    }

    public function testBarelyFaster() {
        // Get data ready that insight requires
        $instance = new Instance();
        $instance->id = 10;
        $instance->network_username = 'testeriffic';
        $instance->network = 'twitter';

        $posts = array();
        $posts[] = new Post(array(
            'reply_count_cache' => 0,
            'retweet_count_cache' => 0,
            'favlike_count_cache' => 44
        ));

        // Add a baseline from prior week
        $last_week = date('Y-m-d', strtotime('-7 day'));
        $builder = FixtureBuilder::build('insight_baselines', array('date'=>$last_week, 'slug'=>'response_count_like',
        'instance_id'=>10, 'value'=>43));

        $insight_plugin = new ResponseTimeInsight();
        $insight_plugin->generateInsight($instance, null, $posts, 3);

        // Assert that insight got inserted
        $insight_dao = new InsightMySQLDAO();
        $today = date ('Y-m-d');
        $result = $insight_dao->getInsight('response_time', 10, $today);
        $this->debug(Utils::varDumpToString($result));
        $this->assertNotNull($result);
        $this->assertNoPattern('/faster/', $result->text);
        $this->assertNoPattern('/slower/', $result->text);
        $this->assertNoPattern('/every.*3.*hour/', $result->text);
        $this->assertPattern('/<strong>3 hours<\/strong>/', $result->headline);
        $this->debug($this->getRenderedInsightInHTML($result));
        $this->debug($this->getRenderedInsightInEmail($result));
    }

    public function testBarelySlower() {
        // Get data ready that insight requires
        $instance = new Instance();
        $instance->id = 10;
        $instance->network_username = 'testeriffic';
        $instance->network = 'twitter';

        $posts = array();
        $posts[] = new Post(array(
            'reply_count_cache' => 0,
            'retweet_count_cache' => 0,
            'favlike_count_cache' => 6
        ));

        // Add a baseline from prior week
        $last_week = date('Y-m-d', strtotime('-7 day'));
        $builder = FixtureBuilder::build('insight_baselines', array('date'=>$last_week, 'slug'=>'response_count_like',
        'instance_id'=>10, 'value'=>7));

        $insight_plugin = new ResponseTimeInsight();
        $insight_plugin->generateInsight($instance, null, $posts, 3);

        // Assert that insight got inserted
        $insight_dao = new InsightMySQLDAO();
        $today = date ('Y-m-d');
        $result = $insight_dao->getInsight('response_time', 10, $today);
        $this->debug(Utils::varDumpToString($result));
        $this->assertNotNull($result);
        $this->assertNoPattern('/slower/', $result->text);
        $this->assertNoPattern('/every day/', $result->text);
        $this->assertPattern('/<strong>day<\/strong>/', $result->headline);
        $this->debug($this->getRenderedInsightInHTML($result));
        $this->debug($this->getRenderedInsightInEmail($result));
    }

    public function testAlternateNoComparisonText() {
        // Get data ready that insight requires
        $instance = new Instance();
        $instance->id = 10;
        $instance->network_username = 'testeriffic';
        $instance->network = 'twitter';

        $posts = array();
        $posts[] = new Post(array( 'reply_count_cache' => 5, 'retweet_count_cache' => 15, 'favlike_count_cache' => 3));
        $posts[] = new Post(array( 'reply_count_cache' => 0, 'retweet_count_cache' => 5, 'favlike_count_cache' => 15));
        $posts[] = new Post(array( 'reply_count_cache' => 2, 'retweet_count_cache' => 5, 'favlike_count_cache' => 1));

        $insight_dao = new InsightMySQLDAO();
        $insight_plugin = new ResponseTimeInsight();

        TimeHelper::setTime(1);
        $insight_plugin->generateInsight($instance, null, $posts, 3);
        $result = $insight_dao->getInsight('response_time', 10, date('Y-m-d'));
        $this->assertEqual("For comparison, the average smartphone owner unlocks their phone 7 times each waking hour.",
            $result->text);

        $this->debug($this->getRenderedInsightInHTML($result));
        $this->debug($this->getRenderedInsightInEmail($result));

        TimeHelper::setTime(2);
        $insight_plugin->generateInsight($instance, null, $posts, 3);
        $result = $insight_dao->getInsight('response_time', 10, date('Y-m-d'));
        $this->assertEqual("That's a healthy share of the 21 million tweets each hour.", $result->text);

        $this->debug($this->getRenderedInsightInHTML($result));
        $this->debug($this->getRenderedInsightInEmail($result));

        TimeHelper::setTime(3);
        $insight_plugin->generateInsight($instance, null, $posts, 3);
        $result = $insight_dao->getInsight('response_time', 10, date('Y-m-d'));
        $this->assertEqual("If you tweeted once every waking hour, that would be roughly 120 times a week.",
            $result->text);

        $posts = array();
        $posts[] = new Post(array( 'reply_count_cache' => 1, 'retweet_count_cache' => 1, 'favlike_count_cache' => 1));
        $posts[] = new Post(array( 'reply_count_cache' => 0, 'retweet_count_cache' => 2, 'favlike_count_cache' => 1));
        $posts[] = new Post(array( 'reply_count_cache' => 0, 'retweet_count_cache' => 0, 'favlike_count_cache' => 1));

        $instance->network = 'facebook';
        TimeHelper::setTime(1);
        $insight_plugin->generateInsight($instance, null, $posts, 3);
        $result = $insight_dao->getInsight('response_time', 10, date('Y-m-d'));
        $this->assertEqual("The average person sneezes 4 times or less each day, just for reference.", $result->text);

        $this->debug($this->getRenderedInsightInHTML($result));
        $this->debug($this->getRenderedInsightInEmail($result));

        TimeHelper::setTime(2);
        $insight_plugin->generateInsight($instance, null, $posts, 3);
        $result = $insight_dao->getInsight('response_time', 10, date('Y-m-d'));
        $this->assertEqual("If you posted once every waking hour, that would be roughly 120 times a week.",
            $result->text);

        $this->debug($this->getRenderedInsightInHTML($result));
        $this->debug($this->getRenderedInsightInEmail($result));

        // do we loop around and skip twitter specific text?
        TimeHelper::setTime(3);
        $insight_plugin->generateInsight($instance, null, $posts, 3);
        $result = $insight_dao->getInsight('response_time', 10, date('Y-m-d'));
        $this->assertEqual("The average person sneezes 4 times or less each day, just for reference.", $result->text);


        $this->debug($this->getRenderedInsightInHTML($result));
        $this->debug($this->getRenderedInsightInEmail($result));
    }

    public function testNoHealthyShareLikes() {
        // Get data ready that insight requires
        $instance = new Instance();
        $instance->id = 10;
        $instance->network_username = 'testeriffic';
        $instance->network = 'twitter';

        $posts = array();
        $posts[] = new Post(array( 'reply_count_cache' => 5, 'retweet_count_cache' => 15, 'favlike_count_cache' => 3));
        $posts[] = new Post(array( 'reply_count_cache' => 0, 'retweet_count_cache' => 5, 'favlike_count_cache' => 15));
        $posts[] = new Post(array( 'reply_count_cache' => 2, 'retweet_count_cache' => 5, 'favlike_count_cache' => 1));

        $insight_dao = new InsightMySQLDAO();
        $insight_plugin = new ResponseTimeInsight();

        TimeHelper::setTime(2);
        $insight_plugin->generateInsight($instance, null, $posts, 3);
        $result = $insight_dao->getInsight('response_time', 10, date('Y-m-d'));
        $this->assertEqual("That's a healthy share of the 21 million tweets each hour.", $result->text);

        $posts = array();
        $posts[] = new Post(array( 'reply_count_cache' => 5, 'retweet_count_cache' => 15, 'favlike_count_cache' => 13));
        $posts[] = new Post(array( 'reply_count_cache' => 0, 'retweet_count_cache' => 5, 'favlike_count_cache' => 15));
        $posts[] = new Post(array( 'reply_count_cache' => 2, 'retweet_count_cache' => 5, 'favlike_count_cache' => 15));

        TimeHelper::setTime(2);
        $insight_plugin->generateInsight($instance, null, $posts, 3);
        $result = $insight_dao->getInsight('response_time', 10, date('Y-m-d'));
        $this->assertNotEqual("That's a healthy share of the 21 million tweets each hour.", $result->text);
    }
}
