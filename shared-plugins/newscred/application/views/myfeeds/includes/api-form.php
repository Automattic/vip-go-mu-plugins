<div style="display:none">
    <div id='inline_content' style="padding:10px; background:#fff;">

        <div id="nc-editors-picks-div" style="width: 100%">
            <form action="" method="post" id="myfeed-api-form">
                <h2>Create Api Call</h2>
                <ul id="content-filters">
                    <li>
                        <span>
                         Categories<label for="selectAll">
                               <input type="checkbox" name="selectAll" value="1" id="cat-select-all">Select All</label>
                        </span>
                        <div class="nc-api-cat-list allCheckbox clearfix">
                            <label for="world"><input class="myfeed-category-chk" type="checkbox" name="categories[]" value="world" id="world">World</label>
                            <label for="us"><input class="myfeed-category-chk"  type="checkbox" name="categories[]" value="u-s" id="us">U.S.</label>
                            <label for="uk"><input class="myfeed-category-chk"  type="checkbox" name="categories[]" value="u-k" id="uk">U.K.</label>
                            <label for="europe"><input class="myfeed-category-chk"  type="checkbox" name="categories[]" value="europe" id="europe">Europe</label>
                            <label for="asia"><input class="myfeed-category-chk"  type="checkbox" name="categories[]" value="asia" id="asia">Asia</label>
                            <label for="africa"><input class="myfeed-category-chk"  type="checkbox" name="categories[]" value="africa" id="africa">Africa</label>
                            <label for="south-america"><input class="myfeed-category-chk"  type="checkbox" name="categories[]" value="south-america" id="south-america">South America</label>
                            <label for="technology"><input class="myfeed-category-chk"  type="checkbox" name="categories[]" value="technology" id="technology">Technology</label>
                            <label for="business"><input class="myfeed-category-chk"  type="checkbox" name="categories[]" value="business" id="business">Business</label>
                            <label for="environment"><input class="myfeed-category-chk"  type="checkbox" name="categories[]" value="environment" id="environment">Environment</label>
                            <label for="health"><input class="myfeed-category-chk"  type="checkbox" name="categories[]" value="health" id="health">Health</label>
                            <label for="sports"><input class="myfeed-category-chk"  type="checkbox" name="categories[]" value="sports" id="sports">Sports</label>
                            <label for="entertainment"><input type="checkbox" name="categories[]" value="entertainment" id="entertainment">Entertainment</label>
                            <label for="travel"><input class="myfeed-category-chk"  type="checkbox" name="categories[]" value="travel" id="travel">Travel</label>
                            <label for="lifestyle"><input class="myfeed-category-chk"  type="checkbox" name="categories[]" value="lifestyle" id="lifestyle">LifeStyle</label>
                            <label for="science"><input class="myfeed-category-chk"  type="checkbox" name="categories[]" value="science" id="science">Science</label>
                            <label for="politics"><input class="myfeed-category-chk"  type="checkbox" name="categories[]" value="politics" id="politics">Politics</label>
                            <label for="offbeat"><input  class="myfeed-category-chk" type="checkbox" name="categories[]" value="offbeat" id="offbeat">Offbeat</label>
                            <label for="regional"><input class="myfeed-category-chk"  type="checkbox" name="categories[]" value="regional" id="regional">Regional</label>
                        </div>

                    </li>
                    <li>
                        <span>Options<label for="selectAlloptions">
<!--                            <input type="checkbox" name="selectAlloptions" value="1" id="selectAlloptions">Select All</label>-->
                        </span>
                        <div class="allCheckbox clearfix">
                            <label for="fulltext" title="Only search for full text sources"><input type="checkbox" name="fulltext" id="fulltext" value="true" checked="" />Full Text</label>
                            <label for="has_images" title="Only search for articles with images"><input type="checkbox" checked=""  name="has_images" id="has_images" value="true" />Has Images</label>
                        </div>
                    </li>
                    <li>
                        <div class="left">
                            <span title="Select the sources you want content from">Sources</span>
                            <input type="text" name="source_guids" id="source_guids" value="" class="nc-text" />
                        </div>
                        <div class="clearfix"></div>
                    </li>

                    <li>
                        <div class="left">
                            <span title="Select the topics you want content on">Topics</span>
                            <input type="text" name="topic_guids" id="topic_guids" value="" class="nc-text" />
                        </div>
                        <div class="clearfix"></div>
                    </li>


                    <li class="nc-top-border">
                        <input type="hidden" name="action" value="ncajax-create-api-call" />
                        <input type="hidden" name="nc_create_apicall_nonce" id="nc_create_apicall_nonce" value="" />
                        <input type="submit" class="button-primary pull-right" name="article_settings_submit" value="Create Api Call" />
                        <img class="nc-search-loading-right" src="<?php echo esc_url( NC_IMAGES_URL ."/nc-loading.gif");?>" />

                    </li>

                </ul>
            </form>
        </div>
    </div>
</div>