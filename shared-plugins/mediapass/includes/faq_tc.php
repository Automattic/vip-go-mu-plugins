<div class="wrap">
	<h2 class="header"><img src="<?php echo plugins_url('/images/logo-icon.png', dirname(__FILE__)) ?>" class="mp-icon" /><span>FAQs</span></h2>

	<?php if (!empty($faq_items)): ?>
		<?php foreach ($faq_items as $faq_item): ?>
			<?php echo $faq_item->get_content(); ?>
		<?php endforeach ?>
	<?php endif ?>
	<h3>What are subscription styles are available?</h3>
	
	<h4>Page Overlay</h4>
	<p>The Page Overlay is for Premium pages on your site, requiring a subscription to access a page and dimming the content until the user signs up. With this option, the user can not interact with any elements on the page - images, text, links, etc, until a subscription is purchased.</p>
	<p>Wrap the post content with [mpoverlay]My Text Here[/mpoverlay] when you edit your posts</p>
	
	<h4>In-Page Overlay</h4>
	<p>The In-Page option is best for Premium sections within a page (articles, blog posts, etc). Unlike the Page Overlay, the In-Page doesn't cover up all the content, but instead sits within a page, hiding the Premium content until a user purchases a subscription.</p>
	<p>To manually select teaser text, wrap you premium content with [mpinpage]My Text Here[/mpinpage] when you edit your posts</p>
	
	<h4>Video Overlay</h4>
	<p>For publishers with video content the Video Overlay is a subscription option that works with any player. Unlike the Page Overlay, users can still interact with other elements - images, text, links, etc - on the page, as well as navigate around to other pages on your site. Similar to the In-Page option, the Video Overlay can be customized to appear after a "teaser" preview of the video.</p>
	
	<h2><?php echo __("Terms &amp; Conditions") ?></h2>

	 <h3 class="c8 c16">
        <span class="c0">DIGITAL SUBSCRIPTION SOLUTION AGREEMENT</span></h3>
    <p class="c12 c15">
        <span class="c5 c1">This DIGITAL SUBSCRIPTION SOLUTION AGREEMENT (this &quot;</span><span
            class="c0 c5">Agreement</span><span class="c5 c1">&quot;) is between You (both the individual
                activating a Publisher Account (as defined below) and, if applicable, the legal
                entity on behalf of which such individual is acting) (&quot;</span><span class="c0 c5">Publisher</span><span
                    class="c5 c1">&quot; or &quot;</span><span class="c0 c5">You</span><span class="c5 c1">&quot;
                        or &quot;</span><span class="c0 c5">Your</span><span class="c5 c1">&quot;) and MediaPass,
                            LLC, with offices at 12100 Wilshire, Suite 125, Los Angeles, CA 90025 (&quot;</span><span
                                class="c0 c5">MediaPass</span><span class="c1 c5">&quot; or &quot;</span><span class="c0 c5">We</span><span
                                    class="c5 c1">&quot; or &quot;</span><span class="c0 c5">Our</span><span class="c5 c1">&quot;).
                                        You acknowledge and agree that by accepting the terms of this Agreement, a binding
                                        agreement is concluded between You and MediaPass.</span></p>
    <h4 class="c8">
        <span>For adequate consideration, the sufficiency of which is hereby acknowledged, the
            parties hereby agree as follows:</span></h4>
    <h4 class="c8">
        <span class="c3">1&nbsp;&nbsp;DEFINITIONS</span></h4>
    <p class="c4">
        <span class="c1">1.1&nbsp;&nbsp;&nbsp;&nbsp;&ldquo;</span><span
            class="c0">Digital Content</span><span class="c1">&rdquo; means any and all content,
                materials and proprietary information made available by You to MediaPass for management
                through the Solution Services, including all text, software, photographs, video,
                graphics, music and sound contained therein.</span></p>
    <p class="c4">
        <span class="c1">1.2&nbsp;&nbsp;&nbsp;&nbsp;&ldquo;</span><span
            class="c0">Effective Date</span><span class="c1">&rdquo; means the date upon which You
                affirmatively accept the terms of this Agreement either electronically or in writing.
            </span>
    </p>
    <p class="c4">
        <span class="c1">1.3&nbsp;&nbsp;&nbsp;&nbsp;&ldquo;</span><span
            class="c0">MediaPass Code</span><span class="c1">&rdquo; means each of the following:
                &nbsp;(a) the MediaPass proprietary Java Code which enables MediaPass to recognize
                Your Digital Content and to sell access to Your Digital Content to Purchasers; and
                (b) the MediaPass proprietary mobile SDK that enables the management and sale of
                access to Your Digital Content via mobile apps. &nbsp;The MediaPass Code can be
                downloaded by You from the MediaPass Website after activating Your Publisher Account.</span></p>
    <p class="c4">
        <span class="c1">1.4&nbsp;&nbsp;&nbsp;&nbsp;&ldquo;</span><span
            class="c0">MediaPass Site</span><span class="c1">&rdquo; means MediaPass&rsquo; internet
                site, </span><span class="c2 c13 c1"><a class="c9" href="http://www.mediapass.com">www.mediapass.com</a></span><span
                    class="c1">, through which MediaPass provides the Solution Services.</span></p>
    <p class="c4">
        <span class="c1">1.5&nbsp;&nbsp;&nbsp;&nbsp;&ldquo;</span><span
            class="c0">Publisher Account</span><span class="c1">&rdquo; means Publisher&rsquo;s
                online account with MediaPass through which You can make Your Digital Content available
                for resale.</span></p>
    <p class="c4">
        <span class="c1">1.6&nbsp;&nbsp;&nbsp;&nbsp;&ldquo;</span><span
            class="c0">Purchasers</span><span class="c1">&rdquo; means those end users of the MediaPass
                Website who have activated an account with MediaPass and have elected to purchase
                Publisher&rsquo;s Digital Content.</span></p>
    <p class="c4">
        <span class="c1">1.7&nbsp;&nbsp;&nbsp;&nbsp;&ldquo;</span><span
            class="c0">Solution Services</span><span class="c1">&rdquo; means the online digital
                transaction management services that MediaPass provides to Publisher, pursuant to
                this Agreement, with respect to the Digital Content.</span></p>
    <p class="c4">
        <span class="c1">1.8&nbsp;&nbsp;&nbsp;&nbsp;&ldquo;</span><span
            class="c0">Your Site</span><span class="c1">&rdquo; means any internet site owned or
                operated by You or on Your behalf where the Digital Content is located.</span></p>
    <h4 class="c8">
        <span class="c3">2&nbsp;&nbsp;USE OF SOLUTION SERVICES</span></h4>
    <p class="c4">
        <span class="c1">During the Term (as defined below) and subject to the terms and conditions
            of this Agreement, You will have the ability to download the MediaPass Code and
            make available Digital Content through the Solution Services, and You shall receive
            monthly payments, as specified in </span><span class="c2 c1">Section 7</span><span
                class="c1">&nbsp;below.</span></p>
    <h4 class="c8">
        <span class="c3">3&nbsp;&nbsp;USE OF PUBLISHER CONTENT</span></h4>
    <p class="c4">
        <span class="c1">3.1&nbsp;&nbsp;&nbsp;&nbsp;</span><span
            class="c2 c1">General</span><span class="c1">. &nbsp;MediaPass shall provide the MediaPass
                Site and Solution Services to receive and manage Purchaser transactions and accounts
                for access to the Digital Content and to manage the Purchaser database on behalf
                of Publisher. &nbsp;Publisher agrees that MediaPass has the right to access, use
                and sublicense the Digital Content solely for the purpose of providing the Solution
                Services described in this Agreement on behalf of Publisher.</span></p>
    <p class="c4">
        <span class="c1">3.2&nbsp;&nbsp;&nbsp;&nbsp;</span><span
            class="c2 c1">Limitations</span><span class="c1">. MediaPass has no obligation to accept
                or manage transactions with respect to any of Your Digital Content. MediaPass agrees:
                (a) not to make any warranties or representations on behalf of Publisher or its
                licensors, (b) to display the Digital Content only in the exact form in which it
                is received by Purchaser (although MediaPass may alter the format of the Digital
                Content); and (c) promptly cease use and distribution of the Digital Content upon
                receipt of written notice from Publisher in the event that Publisher receives notice
                from its licensors that any or all of the Digital Content is or may be infringing
                on a third party&rsquo;s rights.</span></p>
    <p class="c4">
        <span class="c1">3.3&nbsp;&nbsp;&nbsp;&nbsp;</span><span
            class="c2 c1">Prohibited Actions</span><span class="c1">. You agree that you will not:
                (a) take any actions with respect to your uploading of Digital Content that are
                unlawful, false, misleading, harmful, threatening, embarrassing, abusive, harassing,
                tortious, defamatory, vulgar, obscene, libelous, deceptive, fraudulent, invasive
                of another&rsquo;s privacy, hateful, or contains explicit or graphic descriptions
                or accounts of sexual or violent acts; (b) upload or transmit any Digital Content
                using the MediaPass Site, or embed the MediaPass Code on any webpage on Your Site
                or in any mobile app containing (or which otherwise accesses) any content (i) that
                victimizes, harasses, degrades, or intimidates an individual or group of individuals
                on the basis of any impermissible classification, (ii) infringes any patent, trademark,
                trade secret, copyright, or other intellectual or proprietary right of any party,
                (iii) that you do not have a right to transmit under any law or under any contractual
                or fiduciary relationship, or (iv) (that contains software viruses or any other
                computer code, files, or programs designed to interrupt, destroy, or limit the functionality
                of the MediaPass Site; (c) interfere with or disrupt the MediaPass Site or servers
                or networks linked to the MediaPass Site, or disobey any requirements, procedures,
                policies, or regulations of networks linked to the MediaPass Site; (d) take any
                actions with respect to your uploading of Digital Content that violate any applicable
                local, state, national, or international law; or (e) upload or transmit using the
                MediaPass Site any Digital Content that would constitute, or would otherwise encourage,
                criminal conduct or conduct that could give rise to civil liability.</span></p>
    <h4 class="c8">
        <span class="c3">4&nbsp;&nbsp;SOLUTION SERVICES</span></h4>
    <p class="c4">
        <span class="c1">4.1&nbsp;&nbsp;&nbsp;&nbsp;</span><span
            class="c2 c1">Provision of Solution Services</span><span class="c1">. MediaPass shall
                use commercially reasonable efforts to provide the Solution Services to You and
                make the MediaPass Site and Digital Content available to Purchasers. Notwithstanding
                the foregoing, You acknowledge and agree that MediaPass&rsquo; performance of the
                Solution Services is contingent upon Your continued performance of this Agreement
                (including, to the extent applicable, Your continued maintenance and hosting of
                Your Site on which the Digital Content is located).</span></p>
    <p class="c4">
        <span class="c1">4.2&nbsp;&nbsp;&nbsp;&nbsp;</span><span
            class="c2 c1">License to MediaPass Code</span><span class="c1">. MediaPass hereby grants
                to You, solely during the term of this Agreement, a limited, non-exclusive right
                and license to download the MediaPass Code and, as applicable, embed the same on
                one or more webpages on Your Site or use the same in the development of a mobile
                app, for the sole purpose of enabling MediaPass to provide the Solution Services
                in accordance with this Agreement. You acknowledge and agree that upon embedding
                the MediaPass Code into a webpage on Your Site, the Digital Content located on such
                webpage will no longer be fully accessible by users of Your Site unless and until
                access to the Digital Content is purchased.</span></p>
    <p class="c4">
        <span class="c1">4.3&nbsp;&nbsp;&nbsp;&nbsp;</span><span
            class="c2 c1">Limitations</span><span class="c1">. You agree that You will not, nor
                permit others to: (a) attempt to reverse engineer, decompile, disassemble, or extract
                any element of and/or otherwise discover any source code, algorithms, methods or
                techniques embodied in the MediaPass Code; (b) modify, transfer, assign, pledge,
                sublicense, rent, lease, sell, resell, or create derivative works based on the MediaPass
                Code; (c) distribute the MediaPass Code; (d) attempt to embed the MediaPass Code
                on any website other than Your Site or in any mobile app other than Your mobile
                app.</span></p>
    <h4 class="c8">
        <span class="c3">5&nbsp;&nbsp;OWNERSHIP</span></h4>
    <p class="c4">
        <span class="c1">5.1&nbsp;&nbsp;&nbsp;&nbsp;As
            between the parties, You and/or Your licensors exclusively retain all intellectual
            property rights in and to the Digital Content and Your Site and mobile app (except
            for the MediaPass Code to be embedded therein). </span>
    </p>
    <p class="c4">
        <span class="c1">5.2&nbsp;&nbsp;&nbsp;&nbsp;As
            between the parties, MediaPass exclusively retains all intellectual property rights
            in and to the Solution Services, the MediaPass Code and the MediaPass Site (except
            for the Digital Content provided therethrough). </span>
    </p>
    <h4 class="c8">
        <span class="c3">6&nbsp;&nbsp;YOUR PERSONAL INFORMATION</span></h4>
    <p class="c4">
        <span class="c1">In the course of the performance of the Solution Services, MediaPass
            may have access to and/or receive from You certain of Your personal information.
            If MediaPass receives any such personal information, You agree that such personal
            information may be used and disclosed by MediaPass in accordance with MediaPass&rsquo;
            Privacy Policy which has been made available to You and is incorporated herein by
            this reference.</span></p>
    <h4 class="c8">
        <span class="c3">7&nbsp;&nbsp;PAYMENTS</span></h4>
    <p class="c4">
        <span class="c1">7.1&nbsp;&nbsp;&nbsp;&nbsp;</span><span
            class="c2 c1">General</span><span class="c1">. &nbsp;</span></p>
    <p class="c4">
        <span class="c1">(a)&nbsp;&nbsp;&nbsp;&nbsp;</span><span
            class="c2 c1">Publisher Payments</span><span class="c1">. &nbsp;Subject to Your compliance
                with this Agreement, on a monthly basis, MediaPass shall remit to you all Standard
                Gross Revenues (as defined below) received, net of a service fee of twenty percent
                (20%) retained by MediaPass in consideration of the Solution Services provided hereunder
                (collectively, &ldquo;</span><span class="c0">Publisher Payments</span><span class="c1">&rdquo;).
                    &nbsp;For purposes of this Agreement, &ldquo;</span><span class="c0">Standard Gross</span><span
                        class="c0 c11">&nbsp;</span><span class="c0">Revenues</span><span class="c1">&rdquo;
                            means the gross amounts actually received by MediaPass each month from sales of
                            Your Digital Content through the MediaPass Site, less any refunds and charge-backs.
                            For the avoidance of doubt, Standard Gross Revenues shall not include any App Store
                            Gross Revenues (as defined below).</span></p>
    <p class="c4">
        <span class="c1">(b)&nbsp;&nbsp;&nbsp;&nbsp;</span><span
            class="c2 c1">MediaPass Fees</span><span class="c1">. &nbsp;On a monthly basis, You
                will retain all App Store Gross Revenues (as defined below) You receive, net of
                a service fee of ten percent (10%) payable to MediaPass in consideration of the
                Solution Services provided hereunder (the &ldquo;</span><span class="c0">MediaPass Fee</span><span
                    class="c1">&rdquo;). &nbsp;For purposes of this Agreement, &ldquo;</span><span class="c0">App
                        Store Gross</span><span class="c0 c11">&nbsp;</span><span class="c0">Revenues</span><span
                            class="c1">&rdquo; means the gross amounts actually received by Publisher each month
                            from sales of Digital Content through a Native App Store (as defined below), less
                            any refunds and charge-backs. &nbsp;&ldquo;</span><span class="c0">Native App Store</span><span
                                class="c1">&rdquo; means an online app store service (including without limitation
                                Apple&rsquo;s App Store and Google Play) through which: &nbsp;(i) access to the
                                Digital Content is sold through an account of Publisher on such service; and (ii)
                                the provider of such service (e.g., Apple) retains a percentage of revenue received
                                from such transactions.</span></p>
    <p class="c4">
        <span class="c1">(c)&nbsp;&nbsp;&nbsp;&nbsp;</span><span
            class="c2 c1">No Other Compensation</span><span class="c1">. &nbsp;Except for the compensation
                expressly described in this </span><span class="c2 c1">Section 7.1</span><span class="c1">,
                    You shall not be entitled to any other revenue or fees under this Agreement. You
                    agree to bear Your own costs and expenses incurred in performing Your obligations
                    hereunder.</span></p>
    <p class="c4">
        <span class="c1">7.2&nbsp;&nbsp;&nbsp;&nbsp;</span><span
            class="c2 c1">Payment Obligations</span><span class="c1">. </span>
    </p>
    <p class="c4">
        <span class="c1">(a)&nbsp;&nbsp;&nbsp;&nbsp;</span><span
            class="c2 c1">Payment of Publisher Payments</span><span class="c1">. &nbsp;Except as
                otherwise set forth herein, the Publisher Payments shall be payable by MediaPass
                by making an ACH wire transfer or by mailing a payment to You before the thirtieth
                (30th) day of each calendar month following a month during which MediaPass has received
                payment in full from a Purchaser as follows: (a) if payment is by personal check,
                upon the Purchaser&rsquo;s bank honoring such personal check, or (b) if payment
                is by credit card, upon MediaPass receiving payment in full. If MediaPass grants
                any refunds to a Purchaser or suffers any chargebacks or other reversions to payment,
                MediaPass will deduct such amounts from any Publisher Payment which becomes due
                during the same monthly period. In the event that the Publisher Payment owed to
                you in any calendar month is less than US $25.00, such amount will be paid within
                thirty (30) days after the earliest of: (i) the next month in which your total Publisher
                Payment owed is at least US $25.00, (ii) the end of the calendar year, or (iii)
                the termination of this Agreement. </span>
    </p>
    <p class="c4">
        <span class="c1">(b)&nbsp;&nbsp;&nbsp;&nbsp;</span><span
            class="c2 c1">Payment of MediaPass Fees</span><span class="c1">. &nbsp;At the end of
                each calendar month during the Term, MediaPass shall, at its sole election, either:
                &nbsp;(i) invoice You for the amount of MediaPass Fee payable with respect to such
                calendar month; or (ii) deduct the amount of MediaPass Fee payable with respect
                to such calendar month from any Publisher Payments payable to You. &nbsp;If MediaPass
                invoices you for the MediaPass Fee, you shall pay each such invoice within thirty
                (30) days of receipt.</span></p>
    <p class="c4">
        <span class="c1">7.3&nbsp;&nbsp;&nbsp;&nbsp;</span><span
            class="c2 c1">Reports</span><span class="c1">. Through Your Publisher Account You shall
                have the right to access MediaPass&rsquo; reporting tool which will specify the
                quantity of Digital Content purchased, the price paid for such Digital Content and
                the Publisher Payments payable for the same, and a listing of each Purchaser&rsquo;s
                name, address, phone number and email address.</span><span class="c11 c1">&nbsp;</span></p>
    <h4 class="c8">
        <span class="c3">8&nbsp;&nbsp;WARRANTY AND DISCLAIMER</span></h4>
    <p class="c4">
        <span class="c1">8.1&nbsp;&nbsp;&nbsp;&nbsp;</span><span
            class="c2 c1">Warranty by Publisher</span><span class="c1">. You represent and warrant
                that: (a) You have the full power and authority to execute, deliver and perform
                Your obligations under this Agreement; (b) this Agreement is a valid and binding
                obligation enforceable against You in accordance with its terms except as limited
                by applicable bankruptcy, insolvency, reorganization, moratorium, fraudulent conveyance
                or other laws of general application relating to or affecting the enforcement of
                creditors&rsquo; rights generally; (c) Your entry into this Agreement does not violate
                any agreement between You and any third party, and (d) the Digital Content You upload
                or transmit using the MediaPass Site or which is made available or otherwise accessible
                through any webpage on Your Site, or any mobile app, embedded with the MediaPass
                Code is in strict compliance with this Agreement and is not barred by any of the
                prohibited actions set forth in </span><span class="c2 c1">Section 3.3</span><span
                    class="c1">.</span></p>
    <p class="c4">
        <span class="c1">8.2&nbsp;&nbsp;&nbsp;&nbsp;</span><span
            class="c2 c1">Warranty by MediaPass</span><span class="c1">. MediaPass represents and
                warrants that: (a) MediaPass has the full power and authority to execute, deliver
                and perform its obligations under this Agreement; (b) this Agreement is a valid
                and binding obligation enforceable against MediaPass in accordance with its terms
                except as limited by applicable bankruptcy, insolvency, reorganization, moratorium,
                fraudulent conveyance or other laws of general application relating to or affecting
                the enforcement of creditors&rsquo; rights generally; (c) MediaPass&rsquo; entry
                into this Agreement does not violate any agreement between MediaPass and any third
                party; and (d) subject to Your compliance with this Agreement, including Your compliance
                with the prohibited actions set forth in </span><span class="c2 c1">Section 3.3</span><span
                    class="c1">&nbsp;and Your representations and warranties set forth in
        </span><span class="c2 c1">Section 8.1</span><span class="c1">, the Solution Services
            and MediaPass&rsquo; performance under this Agreement conforms to all applicable
            laws, government rules and regulations.</span></p>
    <p class="c4">
        <span class="c1">8.3&nbsp;&nbsp;&nbsp;&nbsp;</span><span
            class="c2 c1">Warranty Disclaimer</span><span class="c1">. EXCEPT AS EXPRESSLY SET FORTH
                IN THIS AGREEMENT, NEITHER PARTY MAKES ANY WARRANTY OF ANY KIND, WHETHER EXPRESS
                OR IMPLIED, FOR ANY GOODS OR SERVICES PROVIDED UNDER THIS AGREEMENT, INCLUDING ANY
                WARRANTY OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE OR NON-INFRINGEMENT.</span></p>
    <h4 class="c8">
        <span class="c3">9&nbsp;&nbsp;LIMITATION OF LIABILITY</span></h4>
    <p class="c4">
        <span class="c1">MEDIAPASS SHALL NOT BE LIABLE TO YOU OR ANY THIRD PARTY OR OBLIGATED
            WITH RESPECT TO THE SUBJECT MATTER OF THIS AGREEMENT OR UNDER ANY CONTRACT, NEGLIGENCE,
            STRICT LIABILITY OR OTHER LEGAL OR EQUITABLE THEORY FOR (I) ANY AMOUNTS IN EXCESS
            IN THE AGGREGATE OF THE PUBLISHER PAYMENTS THEN OWED TO YOU BY MEDIAPASS HEREUNDER,
            (II) ANY INDIRECT, INCIDENTAL OR CONSEQUENTIAL DAMAGES, OR (III) PROCUREMENT OF
            SUBSTITUTE GOODS OR SERVICES.</span></p>
    <h4 class="c8">
        <span class="c3">10&nbsp;&nbsp;INDEMNIFICATION</span></h4>
    <p class="c4">
        <span class="c1">10.1&nbsp;&nbsp;</span><span
            class="c2 c1">Indemnification by You</span><span class="c1">. You hereby agree to indemnify
                and hold MediaPass and its officers, directors, employees, affiliates, agents and
                licensors harmless from and against any and all third party claims, liability, losses,
                costs, damages and expenses (including attorney&rsquo;s fees) arising out of or
                in connection with: (a) Your Site and/or the Digital Content; including without
                limitation, any claim that the Digital Content infringes or violates any copyrights,
                trademarks, trade secrets, patents or other proprietary rights of any kind belonging
                to any third party or violates any right of privacy, right to publicity or misappropriates
                anyone&rsquo;s name or likeness or other rights; (b) Your breach of any provision
                of this Agreement; and/or (c) Your negligence or intentional misconduct.</span></p>
    <p class="c4">
        <span class="c1">10.2&nbsp;&nbsp;</span><span
            class="c2 c1">Notice of and Restrictions on Indemnification</span><span class="c1">.
                In the event that MediaPass seeks indemnification from You in accordance with this
            </span><span class="c2 c1">Section 10</span><span class="c1">&nbsp;in connection with
                the assertion of any threats, claims and proceedings by a third person (a &ldquo;</span><span
                    class="c0">Third Person Assertion</span><span class="c1">&rdquo;), MediaPass will provide
                        You with: (a) prompt written notice of all Third Party Assertions related thereto,
                        (b) reasonable assistance, and (c) control over such defense or settlement as a
                        condition to receiving indemnification. You agree that You will not settle any Third
                        Party Assertion without MediaPass&rsquo; prior written consent, which shall not
                        be unreasonably withheld, conditioned or delayed.</span></p>
    <h4 class="c8">
        <span class="c3">11&nbsp;&nbsp;TERM AND TERMINATION</span></h4>
    <p class="c4">
        <span class="c1">11.1&nbsp;&nbsp;</span><span
            class="c2 c1">Term</span><span class="c1">. This Agreement shall commence on the Effective
                Date and shall continue in full force and effect until the end of the period, if
                any, specified in your Publisher Account or until terminated in accordance with
            </span><span class="c2 c1">Section 11.2</span><span class="c1">&nbsp;(&ldquo;</span><span
                class="c0">Term</span><span class="c1">&rdquo;).</span></p>
    <p class="c4">
        <span class="c1">11.2&nbsp;&nbsp;</span><span
            class="c2 c1">Termination for Cause</span><span class="c1">. Either party may terminate
                this Agreement immediately, without further obligation to the other party in the
                event of any material breach of this Agreement by the other party that is not remedied
                within thirty (30) days&rsquo; of receipt of written notice of such breach; provided
                that MediaPass may terminate this Agreement immediately for any breach of
        </span><span class="c2 c1">Sections 3.3 or 4.3</span><span class="c1">.</span></p>
    <p class="c4">
        <span class="c1">11.3&nbsp;&nbsp;</span><span
            class="c2 c1">Withdrawal of a Service</span><span class="c1">. MediaPass may, in MediaPass&rsquo;
                sole discretion, cancel, modify or delay all or part of the Solution Services upon
                thirty (30) days prior notice to Publisher. If it is mutually agreed that such change
                in the Solution Services materially affects You, You may terminate this Agreement
                upon thirty (30) days&rsquo; written notice.</span></p>
    <p class="c4">
        <span class="c1">11.4&nbsp;&nbsp;</span><span
            class="c2 c1">Obligations Upon Termination</span><span class="c1">. Within thirty (30)
                days of termination of this Agreement for any reason, (a) MediaPass will cease to
                make the Digital Content available for resale and (b) You will remove the MediaPass
                Code from any and all webpages of Your Site and any of your mobile apps. The provisions
                of </span><span class="c2 c1">Sections 1, 3.3, 4.3, 5</span><span class="c1">&nbsp;(except
                    that Publisher may no longer mention Purchaser&rsquo;s name in marketing materials
                    after contract termination), </span><span class="c2 c1">6, 8.3, 9, 10, 11.4, 12, 13
                        and 14</span><span class="c1">&nbsp;and the obligation to pay all accrued fees will
                            survive the termination of this Agreement.</span></p>
    <h4 class="c8">
        <span class="c3">12&nbsp;&nbsp;NOTICES</span></h4>
    <p class="c4">
        <span class="c1">MediaPass may give notice to You by electronic mail to Your last known
            e-mail address on record in Your Publisher Account, or by written communication
            sent by first class mail, postage prepaid, or overnight courier to Your last known
            address on record in Your Publisher Account. You may give notice to MediaPass by
            electronic mail to </span><span class="c2 c1 c13"><a class="c9" href="mailto:support@mediapass.com">
                support@mediapass.com</a></span><span class="c1">&nbsp;or by first class mail, postage
                    pre-paid, or overnight courier to 12100 Wilshire Blvd, Suite 125, Los Angeles, CA
                    90025, Attn: Publisher Accounts.</span></p>
    <h4 class="c8">
        <span class="c3">13&nbsp;&nbsp;THIRD PARTY RIGHTS;
            COMPLAINTS</span></h4>
    <p class="c4">
        <span class="c1">13.1&nbsp;&nbsp;</span><span
            class="c2 c1">Third Party Rights</span><span class="c1">. You shall be solely responsible
                for all licensing, reporting and payment obligations to any third parties in connection
                with Your Digital Content, the resale thereof by MediaPass in accordance with the
                terms of this Agreement, or the use of such Digital Content by Purchasers. MediaPass
                shall not be responsible to any third parties under this Agreement. You shall not
                copy text, photos, pictures or other intellectual property from any third party
                or source (each, a &ldquo;Rights Owner&rdquo;) without specific permission from
                the applicable Rights Owner.</span></p>
    <p class="c4">
        <span class="c1">13.2&nbsp;&nbsp;</span><span
            class="c2 c1">Complaints.</span><span class="c1">&nbsp;</span></p>
    <p class="c7">
        <span class="c1">(a)&nbsp;&nbsp;</span><span
            class="c2 c1">Cooperation</span><span class="c1">. Within forty-eight (48) hours of
                any notification to You by MediaPass that it has received a complaint from a third
                party regarding Your Digital Content, You shall respond to MediaPass by providing
                any facts and evidence requested by MediaPass relevant to the complaint.</span></p>
    <p class="c7">
        <span class="c1">(b)&nbsp;&nbsp;</span><span
            class="c2 c1">Remedial Action</span><span class="c1">. If You breach the terms of this
                Agreement and/or MediaPass receives a report by a Rights Owner or any other third
                party that all or any portion of Your Digital Content contains third party rights
                or intellectual property that is being used without their permission or infringes
                upon their intellectual property rights in any way, or is otherwise inappropriate
                (each, a &ldquo;</span><span class="c0">Complaint</span><span class="c1">&rdquo;), MediaPass
                    reserves the right to automatically remove or make unavailable the applicable Digital
                    Content immediately or within such other timescales as may be decided from time
                    to time by MediaPass following its investigation. The Digital Content shall be taken
                    down or made unavailable without any admission as to liability and without prejudice
                    to any rights, remedies or defenses, all of which are expressly reserved by MediaPass.
                    Service Provider acknowledges and agrees that MediaPass is under no obligation to
                    put back such Digital Content at any time regardless of whether or not it has received
                    a valid Complaint. If all or any portion of Your Digital Content is taken down from
                    the MediaPass Site or otherwise made unavailable for resale by MediaPass as a result
                    of Your breach of this Agreement, Our receipt of a Complaint, or for any other reason,
                    You acknowledge and agree that You are prohibited from re-uploading such Digital
                    Content or embedding the MediaPass Code on any webpage on Your Site containing (or
                    which otherwise accesses) such Digital Content, or in any mobile app, at any time
                    unless and until You receive MediaPass&rsquo; prior written consent to do so.</span></p>
    <h4 class="c8">
        <span class="c3">14&nbsp;&nbsp;GENERAL PROVISIONS</span></h4>
    <p class="c4">
        <span class="c1">14.1&nbsp;&nbsp;</span><span
            class="c2 c1">Governing Law</span><span class="c1">. This Agreement and all matters
                arising out of or relating to this Agreement shall be governed by the internal laws
                of the State of California without giving effect to any choice of law rule. This
                Agreement shall not be governed by the United Nations Convention on Contracts for
                the International Sales of Goods, the application of which is expressly excluded.
                Each party hereby irrevocably consents to the exclusive jurisdiction and venue of
                the state and federal courts located in Los Angeles County, California in connection
                with any claim, action, suit, or proceeding relating to this Agreement, except that
                either party may seek injunctive, equitable or similar relief from any court of
                competent jurisdiction.</span></p>
    <p class="c4">
        <span class="c1">14.2&nbsp;&nbsp;</span><span
            class="c2 c1">Severability and Waiver</span><span class="c1">. If any provision of this
                Agreement is held to be illegal, invalid or otherwise unenforceable, such provision
                will be enforced to the extent possible consistent with the stated intention of
                the parties, or, if incapable of such enforcement, will be deemed to be severed
                and deleted from this Agreement, while the remainder of this Agreement will continue
                in full force and effect. The waiver by either party of any default or breach of
                this Agreement will not constitute a waiver of any other or subsequent default or
                breach.</span></p>
    <p class="c4">
        <span class="c1">14.3&nbsp;&nbsp;</span><span
            class="c2 c1">Independent Contractors</span><span class="c1">. The parties hereto are
                independent contractors. No employment, partnership, or joint venture relationship
                is created by this Agreement. As such, except with respect to the Publisher Payments,
                neither You nor anyone employed by or acting on Your behalf shall receive nor be
                entitled to any consideration, compensation or benefits of any kind from MediaPass;
                and MediaPass shall not be liable for employment taxes respecting MediaPass nor
                any of Your employees.</span></p>
    <p class="c4">
        <span class="c1">14.4&nbsp;&nbsp;</span><span
            class="c1 c2">No Assignment</span><span class="c1">. You may not assign, sell, transfer,
                delegate or otherwise dispose of, whether voluntarily or involuntarily, by operation
                of law or otherwise, this Agreement or any rights or obligations under this Agreement
                without the prior written consent of MediaPass, which may be withheld in MediaPass&rsquo;
                sole discretion. Any purported assignment, transfer or delegation by You shall be
                null and void. MediaPass shall have the right to assign this Agreement without Your
                consent and without prior notice to You. Subject to the foregoing, this Agreement
                shall be binding upon and shall inure to the benefit of the parties and their respective
                successors and assigns.</span></p>
    <p class="c4">
        <span class="c1">14.5&nbsp;&nbsp;</span><span
            class="c2 c1">Injunctive Relief</span><span class="c1">. You acknowledge and agree that
                a breach or threatened breach of any covenant contained in this Agreement would
                cause irreparable injury, that money damages would be an inadequate remedy and that
                MediaPass shall be entitled to temporary and permanent injunctive relief, without
                the posting of any bond or other security, to restrain You, from such breach or
                threatened breach. Nothing in this </span><span class="c2 c1">Section 14.5</span><span
                    class="c1">&nbsp;shall be construed as preventing MediaPass from pursuing any and
                    all remedies available to it, including the recovery of money damages from You.</span></p>
    <p class="c4">
        <span class="c1">14.6&nbsp;&nbsp;</span><span
            class="c2 c1">Entire Agreement</span><span class="c1">. This Agreement along with Our
                Privacy Policy and Website Terms of Service, constitutes the entire agreement between
                the parties and supersedes all prior or contemporaneous agreements or representations,
                written or oral, concerning the subject matter of this Agreement. To the extent
                of any inconsistency between this Agreement and Our Website Terms of Service, the
                relevant terms of this Agreement shall control and prevail. This Agreement may not
                be modified or amended by either party except in a writing signed by a duly authorized
                representative of each party; and no other act, document, usage or custom by You
                shall be deemed to amend or modify this Agreement.</span></p>
</div>