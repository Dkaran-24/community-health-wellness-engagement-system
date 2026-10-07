-- =====================================================================
-- NEW LIFE FITNESS — CEP DEMO / SAMPLE DATA  (DEVELOPMENT ONLY)
-- ---------------------------------------------------------------------
-- ⚠  IMPORTANT — THIS IS DEMO DATA, NOT REAL COMMUNITY RESULTS.
--    These rows exist so the platform can be demonstrated and tested
--    before real community activities take place. For the final college
--    demonstration, use data captured from REAL community activities
--    wherever possible. The Community Impact dashboard always states
--    whether figures are from demo data or live data.
--
-- Dates are computed RELATIVE TO TODAY (CURDATE() arithmetic) so the
-- demo always shows a realistic mix of past and upcoming events.
--
-- Import AFTER cep_install.sql, into the CEP database only.
--
-- Demo community login (password = "Community@123", bcrypt):
--   priya.sharma@community.demo / Community@123
--   rahul.verma@community.demo / Community@123
--   (all demo users share the same demo password)
-- =====================================================================

-- ------------------- COMMUNITY USERS (12 demo residents) -------------
INSERT INTO community_users
  (full_name, email, mobile, age, gender, address_area, password,
   fitness_level, fitness_goal, preferred_activities, status)
VALUES
('Priya Sharma',   'priya.sharma@community.demo',   '9000000001', 28, 'Female', 'Riverside Colony',        '$2y$10$AsgIdSNJ1kZ5WjCK2bxn0e64IgWvjMWHtxThl7P8QavThK3oa/IXC', 'Beginner',     'General Fitness', 'Yoga, Walking',              'Active'),
('Rahul Verma',    'rahul.verma@community.demo',    '9000000002', 35, 'Male',   'Gandhi Nagar',            '$2y$10$AsgIdSNJ1kZ5WjCK2bxn0e64IgWvjMWHtxThl7P8QavThK3oa/IXC', 'Intermediate', 'Weight Loss',     'Running, Zumba',              'Active'),
('Anjali Deshmukh','anjali.deshmukh@community.demo','9000000003', 42, 'Female', 'Lake View Apartments',    '$2y$10$AsgIdSNJ1kZ5WjCK2bxn0e64IgWvjMWHtxThl7P8QavThK3oa/IXC', 'Beginner',     'Wellness',        'Yoga',                        'Active'),
('Suresh Patil',   'suresh.patil@community.demo',   '9000000004', 61, 'Male',   'Old Town Sector 4',       '$2y$10$AsgIdSNJ1kZ5WjCK2bxn0e64IgWvjMWHtxThl7P8QavThK3oa/IXC', 'Beginner',     'General Fitness', 'Walking',                     'Active'),
('Meera Iyer',     'meera.iyer@community.demo',     '9000000005', 33, 'Female', 'Temple Street',           '$2y$10$AsgIdSNJ1kZ5WjCK2bxn0e64IgWvjMWHtxThl7P8QavThK3oa/IXC', 'Intermediate', 'Flexibility',     'Yoga, Zumba',                 'Active'),
('Arjun Kulkarni', 'arjun.kulkarni@community.demo', '9000000006', 24, 'Male',   'Sports Colony',           '$2y$10$AsgIdSNJ1kZ5WjCK2bxn0e64IgWvjMWHtxThl7P8QavThK3oa/IXC', 'Advanced',     'Strength',        'Running, Fitness Camp',       'Active'),
('Sunita Joshi',   'sunita.joshi@community.demo',   '9000000007', 38, 'Female', 'Ring Road East',          '$2y$10$AsgIdSNJ1kZ5WjCK2bxn0e64IgWvjMWHtxThl7P8QavThK3oa/IXC', 'Beginner',     'Weight Loss',     'Walking, Zumba',              'Active'),
('Vikram Rao',     'vikram.rao@community.demo',     '9000000008', 47, 'Male',   'Hill Park Area',          '$2y$10$AsgIdSNJ1kZ5WjCK2bxn0e64IgWvjMWHtxThl7P8QavThK3oa/IXC', 'Intermediate', 'Endurance',       'Running, Walking',            'Active'),
('Kavita Menon',   'kavita.menon@community.demo',   '9000000009', 55, 'Female', 'Green Valley Society',    '$2y$10$AsgIdSNJ1kZ5WjCK2bxn0e64IgWvjMWHtxThl7P8QavThK3oa/IXC', 'Beginner',     'Wellness',        'Yoga, Senior Fitness',        'Active'),
('Deepak Shetty',  'deepak.shetty@community.demo',  '9000000010', 29, 'Male',   'Market Lane',             '$2y$10$AsgIdSNJ1kZ5WjCK2bxn0e64IgWvjMWHtxThl7P8QavThK3oa/IXC', 'Intermediate', 'Weight Loss',     'Zumba, Fitness Camp',         'Active'),
('Lakshmi Nair',   'lakshmi.nair@community.demo',   '9000000011', 45, 'Female', 'Gandhi Nagar',            '$2y$10$AsgIdSNJ1kZ5WjCK2bxn0e64IgWvjMWHtxThl7P8QavThK3oa/IXC', 'Beginner',     'General Fitness', 'Walking, Nutrition Workshop', 'Active'),
('Rohan Gaikwad',  'rohan.gaikwad@community.demo',  '9000000012', 31, 'Male',   'Riverside Colony',        '$2y$10$AsgIdSNJ1kZ5WjCK2bxn0e64IgWvjMWHtxThl7P8QavThK3oa/IXC', 'Beginner',     'General Fitness', 'Walking, Running',            'Active');

-- ------------------- COMMUNITY EVENTS (10 demo events) ---------------
-- Mix of completed (past) and upcoming, across categories.
INSERT INTO community_events
  (event_name, description, category, event_date, start_time, end_time,
   location, organizer, trainer_id, max_participants, reg_deadline, status)
VALUES
('Sunday Morning Yoga Camp', 'Free community yoga session for all age groups. Mats provided.', 'Yoga', DATE_SUB(CURDATE(), INTERVAL 21 DAY), '06:30:00','08:00:00', 'Community Park - Central Lawn', 'Elena Cruz', 2, 50, DATE_SUB(CURDATE(), INTERVAL 23 DAY), 'Completed'),
('Community Health Check-up Camp', 'Free BP, sugar and BMI screening with doctor consultation.', 'Health Awareness', DATE_SUB(CURDATE(), INTERVAL 14 DAY), '09:00:00','13:00:00', 'Club Community Hall', 'Aisha Khan', 4, 120, DATE_SUB(CURDATE(), INTERVAL 16 DAY), 'Completed'),
('Sunset Zumba Session', 'High-energy dance fitness evening for the whole family.', 'Zumba', DATE_SUB(CURDATE(), INTERVAL 7 DAY), '17:30:00','18:30:00', 'Open Ground - Sector 3', 'Devon Walker', 3, 60, DATE_SUB(CURDATE(), INTERVAL 9 DAY), 'Completed'),
('Morning Walking Club Launch', 'Kick-off of the weekly community walking program.', 'Walking', DATE_ADD(CURDATE(), INTERVAL 3 DAY), '06:00:00','07:00:00', 'Riverside Walking Track', 'Marcus Reid', 1, 100, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'Upcoming'),
('Women''s Wellness Workshop', 'Fitness, nutrition and self-care session for women.', 'Women Wellness', DATE_ADD(CURDATE(), INTERVAL 5 DAY), '10:00:00','12:00:00', 'Club Community Hall', 'Elena Cruz', 2, 40, DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'Upcoming'),
('Nutrition & Healthy Eating Workshop', 'Practical diet planning for families with a nutritionist.', 'Nutrition Workshop', DATE_ADD(CURDATE(), INTERVAL 10 DAY), '11:00:00','13:00:00', 'Club Seminar Room', 'Aisha Khan', 4, 45, DATE_ADD(CURDATE(), INTERVAL 8 DAY), 'Upcoming'),
('Senior Citizens Fitness Program', 'Chair yoga, light stretching and balance exercises for 55+.', 'Senior Fitness', DATE_ADD(CURDATE(), INTERVAL 14 DAY), '07:00:00','08:00:00', 'Community Park - East Gate', 'Elena Cruz', 2, 30, DATE_ADD(CURDATE(), INTERVAL 12 DAY), 'Upcoming'),
('Community Running Meet', '2km & 5km fun run around the lake for all fitness levels.', 'Running', DATE_ADD(CURDATE(), INTERVAL 18 DAY), '06:00:00','08:30:00', 'Lake View Apartments Gate', 'Devon Walker', 3, 80, DATE_ADD(CURDATE(), INTERVAL 16 DAY), 'Upcoming'),
('Free Fitness Camp for Beginners', 'Introductory strength & conditioning camp, no equipment needed.', 'Fitness Camp', DATE_ADD(CURDATE(), INTERVAL 25 DAY), '07:00:00','09:00:00', 'Open Ground - Sector 3', 'Marcus Reid', 1, 60, DATE_ADD(CURDATE(), INTERVAL 23 DAY), 'Upcoming'),
('30-Day Wellness Challenge Finale', 'Closing event of the community wellness challenge with prizes.', 'Community Challenge', DATE_ADD(CURDATE(), INTERVAL 30 DAY), '17:00:00','19:00:00', 'Club Community Hall', 'New Life Fitness Team', NULL, 200, DATE_ADD(CURDATE(), INTERVAL 28 DAY), 'Upcoming');

-- ------------------- EVENT REGISTRATIONS (demo) ---------------------
-- Completed events 1-3: realistic partial attendance; upcoming 4-6: bookings.
INSERT INTO event_registrations (community_user_id, event_id, reg_date, status) VALUES
(1,1, DATE_SUB(NOW(), INTERVAL 23 DAY), 'Attended'),
(2,1, DATE_SUB(NOW(), INTERVAL 22 DAY), 'Attended'),
(3,1, DATE_SUB(NOW(), INTERVAL 23 DAY), 'Attended'),
(5,1, DATE_SUB(NOW(), INTERVAL 21 DAY), 'Missed'),
(9,1, DATE_SUB(NOW(), INTERVAL 22 DAY), 'Attended'),
(1,2, DATE_SUB(NOW(), INTERVAL 16 DAY), 'Attended'),
(4,2, DATE_SUB(NOW(), INTERVAL 15 DAY), 'Attended'),
(7,2, DATE_SUB(NOW(), INTERVAL 16 DAY), 'Attended'),
(8,2, DATE_SUB(NOW(), INTERVAL 15 DAY), 'Attended'),
(11,2, DATE_SUB(NOW(), INTERVAL 14 DAY), 'Missed'),
(2,3, DATE_SUB(NOW(), INTERVAL 9 DAY), 'Attended'),
(6,3, DATE_SUB(NOW(), INTERVAL 8 DAY), 'Attended'),
(7,3, DATE_SUB(NOW(), INTERVAL 9 DAY), 'Attended'),
(10,3, DATE_SUB(NOW(), INTERVAL 8 DAY), 'Missed'),
(5,3, DATE_SUB(NOW(), INTERVAL 9 DAY), 'Attended'),
(1,4, DATE_SUB(NOW(), INTERVAL 1 DAY), 'Registered'),
(4,4, DATE_SUB(NOW(), INTERVAL 1 DAY), 'Registered'),
(12,4, DATE_SUB(NOW(), INTERVAL 0 DAY), 'Registered'),
(3,5, DATE_SUB(NOW(), INTERVAL 2 DAY), 'Registered'),
(5,5, DATE_SUB(NOW(), INTERVAL 2 DAY), 'Registered'),
(7,5, DATE_SUB(NOW(), INTERVAL 3 DAY), 'Registered'),
(9,5, DATE_SUB(NOW(), INTERVAL 2 DAY), 'Registered'),
(11,5, DATE_SUB(NOW(), INTERVAL 3 DAY), 'Registered'),
(2,6, DATE_SUB(NOW(), INTERVAL 7 DAY), 'Registered'),
(7,6, DATE_SUB(NOW(), INTERVAL 8 DAY), 'Registered'),
(11,6, DATE_SUB(NOW(), INTERVAL 7 DAY), 'Registered'),
(9,7, DATE_SUB(NOW(), INTERVAL 12 DAY), 'Registered'),
(4,7, DATE_SUB(NOW(), INTERVAL 12 DAY), 'Registered'),
(2,8, DATE_SUB(NOW(), INTERVAL 15 DAY), 'Registered'),
(6,8, DATE_SUB(NOW(), INTERVAL 15 DAY), 'Registered'),
(8,8, DATE_SUB(NOW(), INTERVAL 16 DAY), 'Registered'),
(12,8, DATE_SUB(NOW(), INTERVAL 15 DAY), 'Registered'),
(1,9, DATE_SUB(NOW(), INTERVAL 20 DAY), 'Registered'),
(10,9, DATE_SUB(NOW(), INTERVAL 21 DAY), 'Registered');

-- ------------------- EVENT ATTENDANCE (demo, completed events) ------
-- Mirrors the registrations of completed events 1-3.
INSERT INTO event_attendance (event_id, community_user_id, status, marked_by, marked_at) VALUES
(1,1,'Present',1, NOW()),
(1,2,'Present',1, NOW()),
(1,3,'Present',1, NOW()),
(1,5,'Absent', 1, NOW()),
(1,9,'Present',1, NOW()),
(2,1,'Present',1, NOW()),
(2,4,'Present',1, NOW()),
(2,7,'Present',1, NOW()),
(2,8,'Present',1, NOW()),
(2,11,'Absent',1, NOW()),
(3,2,'Present',1, NOW()),
(3,6,'Present',1, NOW()),
(3,7,'Present',1, NOW()),
(3,10,'Absent',1, NOW()),
(3,5,'Present',1, NOW());

-- ------------------- VOLUNTEER APPLICATIONS (demo) ------------------
INSERT INTO volunteer_applications
  (community_user_id, skills, interest_areas, availability, previous_experience, reason, status, reviewed_by, reviewed_at)
VALUES
(6,  'Event coordination, crowd management', 'Fitness camps, Running events', 'Weekends', 'College sports fest volunteer (2 years)', 'I want to help the community stay active and give back through sport.', 'Approved', 1, NOW()),
(8,  'First-aid certified, marshalling',     'Running events, Health camps',  'Weekends, early mornings', 'Red Cross volunteer', 'Health of my neighbourhood matters to me; I want to contribute.', 'Approved', 1, NOW()),
(10, 'Photography, social media',            'All community events',          'Evenings',   'Covered local marathons', 'I would love to document community fitness journeys.', 'Approved', 1, NOW()),
(1,  'Yoga instruction (beginner level)',    'Yoga sessions, senior fitness', 'Mornings',   NULL, 'Yoga changed my life; I want to introduce it to more people.', 'Pending', NULL, NULL),
(3,  'Nutrition counselling',                'Nutrition workshops',           'Weekends',   'Dietetics internship', 'Good food habits should reach every family in our area.', 'Pending', NULL, NULL),
(12, 'Public speaking',                      'Health awareness camps',        'Sundays',    NULL, 'I enjoy speaking to groups about healthy living.', 'Rejected', 1, NOW());

-- ------------------- VOLUNTEERS (approved users) --------------------
INSERT INTO volunteers (community_user_id, application_id, status, total_hours) VALUES
(6, 1, 'Active', 14.0),
(8, 2, 'Active', 11.5),
(10,3, 'Active', 6.0);

-- ------------------- VOLUNTEER EVENT ASSIGNMENTS (demo) -------------
INSERT INTO volunteer_event_assignments (volunteer_id, event_id, role, assigned_by, status) VALUES
(1, 4, 'Route Marshal',           1, 'Assigned'),
(1, 8, 'Run Coordinator',         1, 'Assigned'),
(2, 8, 'First-Aid Support',       1, 'Assigned'),
(2, 2, 'Health Desk Volunteer',   1, 'Completed'),
(3, 4, 'Event Photographer',      1, 'Assigned'),
(3, 5, 'Social Media Coverage',   1, 'Assigned');

-- ------------------- VOLUNTEER HOURS (demo) -------------------------
INSERT INTO volunteer_hours (volunteer_id, event_id, work_date, role, start_time, end_time, total_hours, status) VALUES
(1, 2, DATE_SUB(CURDATE(), INTERVAL 14 DAY), 'Health Desk Volunteer', '08:30:00','13:30:00', 5.0, 'Approved'),
(1, 3, DATE_SUB(CURDATE(), INTERVAL 7 DAY),  'Crowd Management',      '17:00:00','19:00:00', 2.0, 'Approved'),
(1, 1, DATE_SUB(CURDATE(), INTERVAL 21 DAY), 'Setup Support',         '06:00:00','08:30:00', 2.5, 'Approved'),
(2, 2, DATE_SUB(CURDATE(), INTERVAL 14 DAY), 'First-Aid Support',     '08:30:00','14:30:00', 6.0, 'Approved'),
(2, 1, DATE_SUB(CURDATE(), INTERVAL 21 DAY), 'First-Aid Support',     '06:00:00','09:00:00', 3.0, 'Approved'),
(3, 3, DATE_SUB(CURDATE(), INTERVAL 7 DAY),  'Event Photographer',    '17:00:00','19:00:00', 2.0, 'Approved'),
(3, 2, DATE_SUB(CURDATE(), INTERVAL 14 DAY), 'Photographer',          '09:00:00','13:00:00', 4.0, 'Approved');

-- ------------------- COMMUNITY FEEDBACK (demo) ----------------------
INSERT INTO community_feedback
  (event_id, community_user_id, rating, satisfaction, comments, suggestions, would_attend_again, sentiment, topics)
VALUES
(1,1,5,'Very Satisfied','Wonderful peaceful session, instructor was very patient with beginners.','More evening sessions please','Yes','Positive','timing,trainer,activity quality'),
(1,2,4,'Satisfied','Good session overall. Reached late because of traffic.','Start at 7 AM instead of 6:30','Yes','Positive','timing,location'),
(1,3,5,'Very Satisfied','Loved the yoga camp, felt very fresh the whole day.',NULL,'Yes','Positive','activity quality,trainer'),
(1,9,4,'Satisfied','Nice event, mats were comfortable. Parking was difficult.','Arrange parking space','Yes','Positive','facilities,location'),
(2,1,5,'Very Satisfied','Very useful health check-up, doctor explained my reports clearly.','Quarterly health camps','Yes','Positive','activity quality'),
(2,4,4,'Satisfied','Good initiative for senior citizens like me. Waiting queue was long.','More doctors next time','Yes','Positive','facilities,timing'),
(2,7,5,'Very Satisfied','Found out my BP is high, grateful for the free screening.',NULL,'Yes','Positive','activity quality'),
(2,8,4,'Satisfied','Well organised camp, volunteers were helpful.','Add a diet desk','Yes','Positive','facilities,trainer'),
(3,2,5,'Very Satisfied','Zumba was so much fun, great energy!','Weekly zumba please','Yes','Positive','activity quality,trainer'),
(3,6,4,'Satisfied','Great workout, music was a bit loud at the end.','Lower the volume','Yes','Positive','facilities'),
(3,7,3,'Neutral','Enjoyed but the ground was dusty.','Water the ground before session','Yes','Neutral','facilities,location'),
(3,5,5,'Very Satisfied','Best evening of the week! Instructor was energetic.','Bring more dance fitness','Yes','Positive','trainer,activity quality');

-- ------------------- SURVEYS & POLLS (demo) -------------------------
INSERT INTO community_surveys (title, description, type, status, created_by, created_at) VALUES
('Which activity should we organise next month?', 'Community poll to decide the next free activity.', 'Poll', 'Open', 1, NOW()),
('Community Wellness Survey', 'Short survey on health needs of our neighbourhood.', 'Survey', 'Open', 1, NOW()),
('Best time for community sessions?', 'Help us pick convenient timings.', 'Poll', 'Closed', 1, DATE_SUB(NOW(), INTERVAL 20 DAY));

INSERT INTO survey_questions (survey_id, question_text, question_type, options) VALUES
(1, 'Which activity would you like us to organize next?', 'single', '["Yoga","Zumba","Walking","Health Camp","Nutrition Workshop"]'),
(2, 'What is your biggest health priority?', 'single', '["Weight management","Stress relief","Better fitness","Healthy eating"]'),
(2, 'Any suggestions for our community program?', 'text', '[]'),
(3, 'Which timing suits you best for weekday sessions?', 'single', '["Early morning (6-8 AM)","Morning (8-10 AM)","Evening (5-7 PM)","Night (7-9 PM)"]');

INSERT INTO survey_responses (survey_id, question_id, community_user_id, option_text, response_text, submitted_at) VALUES
(1,1,1,'Yoga',NULL,NOW()),
(1,1,2,'Zumba',NULL,NOW()),
(1,1,3,'Yoga',NULL,NOW()),
(1,1,5,'Yoga',NULL,NOW()),
(1,1,7,'Walking',NULL,NOW()),
(1,1,9,'Yoga',NULL,NOW()),
(1,1,11,'Health Camp',NULL,NOW()),
(1,1,4,'Walking',NULL,NOW()),
(1,1,12,'Walking',NULL,NOW()),
(1,1,8,'Health Camp',NULL,NOW()),
(2,2,1,'Stress relief',NULL,NOW()),
(2,2,7,'Weight management',NULL,NOW()),
(2,2,3,'Better fitness',NULL,NOW()),
(2,3,3,'Please add weekend meditation sessions.',NULL,NOW()),
(2,3,1,'Morning yoga is perfect for our area.',NULL,NOW()),
(3,4,1,'Early morning (6-8 AM)',NULL,DATE_SUB(NOW(), INTERVAL 19 DAY)),
(3,4,2,'Early morning (6-8 AM)',NULL,DATE_SUB(NOW(), INTERVAL 19 DAY)),
(3,4,3,'Evening (5-7 PM)',NULL,DATE_SUB(NOW(), INTERVAL 19 DAY)),
(3,4,5,'Evening (5-7 PM)',NULL,DATE_SUB(NOW(), INTERVAL 19 DAY)),
(3,4,7,'Early morning (6-8 AM)',NULL,DATE_SUB(NOW(), INTERVAL 19 DAY)),
(3,4,9,'Morning (8-10 AM)',NULL,DATE_SUB(NOW(), INTERVAL 19 DAY));

-- ------------------- COMMUNITY REQUESTS (demo) ----------------------
INSERT INTO community_requests
  (community_user_id, request_type, title, description, status, admin_note, converted_event_id)
VALUES
(3,  'Yoga Sessions',            'Evening yoga for working women', 'Many working women cannot attend morning sessions, requesting 6-7 PM yoga.', 'Scheduled', 'Approved — scheduled as Evening Yoga Batch', 5),
(9,  'Senior Fitness Program',   'Weekly senior citizen exercise class', 'Requesting gentle weekly exercise for elderly residents of Green Valley.', 'Approved', 'Will be added to next month calendar', NULL),
(7,  'Nutrition Workshop',       'Family nutrition workshop',       'Please organise a workshop on healthy tiffin ideas for kids.', 'Under Review', NULL, NULL),
(4,  'Health Camp',              'Quarterly health screening camp', 'Senior residents need regular BP/sugar check-ups nearby.', 'Completed', 'Completed as Community Health Check-up Camp', 2),
(11, 'Women Wellness Program',   'Self-defense + fitness for women', 'A combined safety and fitness program would empower local women.', 'Submitted', NULL, NULL),
(12, 'Other',                    'Open-air movie + fitness night',  'Family fitness evening with activities for kids and parents.', 'Rejected', 'Out of scope for the fitness program this quarter', NULL);

-- ------------------- WELLNESS RESOURCES (demo) ----------------------
INSERT INTO wellness_resources (title, category, summary, content, status, views, created_by) VALUES
('10 Tips to Start Walking Today', 'Beginner Fitness', 'A gentle, practical guide to beginning a walking routine safely.',
 'Walking is the easiest way to begin your fitness journey. Start with 15 minutes at a comfortable pace and add 5 minutes each week. Wear cushioned shoes, keep your back straight, and swing your arms naturally. Aim for a pace where you can still talk but not sing. Hydrate before and after, and choose safe, well-lit routes. Consistency matters more than speed — four short walks a week beat one long exhausting one. Track your progress with a phone app or a simple diary, and celebrate small milestones. If you feel pain in your chest, joints or head, stop and consult a doctor.', 'Published', 34, 1),
('Home Nutrition Basics for Families', 'Nutrition', 'Simple, low-cost healthy eating principles for Indian families.',
 'Healthy eating does not need to be expensive. Build every meal around whole grains (roti, rice, oats), one protein (dal, paneer, eggs, curd) and seasonal vegetables. Keep fried snacks to twice a week and prefer fruit as the default dessert. Cook with less oil, salt and sugar gradually — taste adapts in a few weeks. Drink 6-8 glasses of water a day and limit sugary drinks. Plan a weekly menu to avoid last-minute junk food decisions, and involve children in cooking so they learn good habits early.', 'Published', 21, 1),
('Exercise Safety for Beginners', 'Exercise Safety', 'How to avoid injury when you start a new fitness activity.',
 'Always warm up for 5-10 minutes before any activity and cool down afterwards. Progress gradually — increase duration or intensity by no more than 10% per week. Learn correct form before adding weights or speed; ask a trainer at community events. Wear activity-appropriate footwear and clothing. Exercise should feel challenging but never painful; sharp pain means stop. Rest at least one day a week, sleep 7-8 hours, and eat a light snack 30-60 minutes before longer sessions. Consult a doctor before starting if you have heart problems, diabetes, high BP, joint issues or are above 50 and new to exercise.', 'Published', 18, 1),
('Managing Stress with Breathing & Relaxation', 'Wellness Awareness', 'Simple evidence-backed relaxation techniques you can do anywhere.',
 'Chronic stress raises blood pressure and disturbs sleep. The 4-7-8 technique is a quick relaxation tool: inhale through the nose for 4 seconds, hold for 7, exhale slowly through the mouth for 8; repeat four times. Practise it during commutes or before bed. Combine it with 10 minutes of daily quiet sitting, limiting screen time after 9 PM, and at least 20 minutes of daylight walking. Keep a worry journal to offload racing thoughts. If stress persists for weeks or affects daily life, speak to a counsellor — asking for help is a sign of strength.', 'Published', 12, 1),
('Warm-up & Cool-down Guide', 'Exercise Guide', 'A practical 10-minute routine before and after every session.',
 'Warm-up (5 minutes): march on the spot 60s, arm circles 30s each direction, hip rotations 20 each side, bodyweight squats x10 slow, brisk walk 2 minutes. Cool-down (5 minutes): slow walk 2 minutes, standing quad stretch 20s per leg, shoulder stretch 20s per arm, deep breathing 60s. Never stretch cold muscles aggressively — save deep stretches for after activity when muscles are warm.', 'Published', 9, 1),
('Healthy Lifestyle Habits Checklist', 'Healthy Lifestyle', 'A daily checklist covering sleep, water, movement and food.',
 '1) Sleep 7-8 hours at consistent times. 2) Drink a glass of water after waking. 3) Take at least 6,000 steps or 30 minutes of activity. 4) Eat 2 fruits and 3 vegetable servings. 5) 10 minutes of quiet or breathing time. 6) No screens 30 minutes before bed. 7) One rest day a week from intense exercise. Tick five of seven daily for a healthy week.', 'Published', 15, 1),
('Why Fitness Education Matters', 'Fitness Education', 'Understanding basic fitness concepts empowers better decisions.',
 'Fitness has five components: cardio-respiratory endurance (walking, running), muscular strength (resistance work), muscular endurance (repetitions), flexibility (stretching/yoga) and body composition. A balanced week includes 150 minutes of moderate cardio, two strength sessions and daily stretching. Fitness is a long-term investment — benefits like better sleep, mood, immunity and energy appear within weeks, while disease protection builds over years. Community programs exist so you can learn and practise these basics with guidance and support.', 'Published', 7, 1),
('Strength Training at Any Age', 'Exercise Guide', 'Safe strength basics for adults and seniors using bodyweight only.',
 'Muscle mass declines ~1% yearly after 40 — strength training reverses this. Start with: chair squats (sit-to-stand) 2x8, wall push-ups 2x8, heel raises 2x12, and standing balance on one leg 20s per side. Do this twice a week with a rest day between. Move slowly, breathe out on effort, and stop if joints hurt. Seniors should hold a chair for balance support. After 4-6 weeks progress to lower chairs / deeper squats. Strength work protects bones, prevents falls and keeps you independent.', 'Published', 11, 1);

-- ------------------- ANNOUNCEMENTS (demo) ---------------------------
INSERT INTO community_announcements (title, message, type, status, created_by) VALUES
('Walking Club starts this weekend!', 'Our free community Walking Club kicks off this Sunday 6 AM at the Riverside Walking Track. All are welcome — bring comfortable shoes and a water bottle. No registration fee.', 'Event', 'Active', 1),
('Women''s Wellness Workshop — few seats left', 'The Women''s Wellness Workshop has only a few seats remaining. Register from your community dashboard to reserve your place.', 'Workshop', 'Active', 1),
('Volunteers needed for the Community Running Meet', 'We are looking for route marshals and first-aid volunteers for the Community Running Meet. Approved volunteers can pick up assignments from their volunteer dashboard.', 'Volunteer Opportunity', 'Active', 1),
('New 30-Day Wellness Challenge is live', 'Join the 30-Day Wellness Challenge from the Challenges page. Log your daily progress and win community recognition at the finale event.', 'Challenge', 'Active', 1),
('Free health screening next month', 'Based on your poll responses, another free health screening camp is being planned for next month. Watch this space for dates.', 'Health Camp', 'Active', 1),
('Welcome to New Life Fitness Community!', 'Our community platform is live. Create your free community account to register for events, join challenges, share feedback and volunteer.', 'Notice', 'Active', 1);

-- ------------------- FITNESS CHALLENGES (demo) ----------------------
INSERT INTO fitness_challenges (challenge_name, description, category, target_value, target_unit, start_date, end_date, status) VALUES
('30-Day Wellness Challenge', 'Log a healthy habit every day for 30 days — walking, yoga, healthy eating or meditation.', 'General Fitness', 30, 'days', DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_ADD(CURDATE(), INTERVAL 20 DAY), 'Active'),
('10,000 Steps Challenge', 'Walk 10,000 steps a day for two weeks and log your daily steps.', 'Steps', 14, 'days', DATE_ADD(CURDATE(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 19 DAY), 'Upcoming'),
('21-Day Yoga Challenge', 'Practice 20 minutes of yoga daily for 21 days.', 'Yoga', 21, 'days', DATE_SUB(CURDATE(), INTERVAL 35 DAY), DATE_SUB(CURDATE(), INTERVAL 14 DAY), 'Completed'),
('Community Walking Challenge', 'Walk 40 km in a month with the community walking club.', 'Walking', 40, 'km', DATE_SUB(CURDATE(), INTERVAL 40 DAY), DATE_SUB(CURDATE(), INTERVAL 10 DAY), 'Completed');

INSERT INTO challenge_participants (challenge_id, community_user_id, current_value, completed, completed_at, joined_at) VALUES
(1,1,10,0,NULL, NOW()),
(1,2,8,0,NULL, NOW()),
(1,7,11,0,NULL, NOW()),
(1,11,6,0,NULL, NOW()),
(2,1,0,0,NULL, NOW()),
(2,4,0,0,NULL, NOW()),
(3,3,21,1,DATE_SUB(NOW(), INTERVAL 14 DAY), DATE_SUB(NOW(), INTERVAL 35 DAY)),
(3,5,19,0,NULL, DATE_SUB(NOW(), INTERVAL 35 DAY)),
(3,9,21,1,DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 34 DAY)),
(4,4,40,1,DATE_SUB(NOW(), INTERVAL 12 DAY), DATE_SUB(NOW(), INTERVAL 40 DAY)),
(4,11,38,0,NULL, DATE_SUB(NOW(), INTERVAL 40 DAY)),
(4,12,35,0,NULL, DATE_SUB(NOW(), INTERVAL 38 DAY));

INSERT INTO challenge_progress (participant_id, progress_date, value_logged, notes) VALUES
(1, CURDATE(), 1, 'Morning walk 30 min'),
(1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 1, 'Yoga + healthy meals'),
(1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 1, 'Evening walk'),
(2, CURDATE(), 1, 'Gym + 8k steps'),
(2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 1, 'Zumba session'),
(3, CURDATE(), 1, 'Walk with neighbours'),
(3, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 1, 'Healthy tiffin day'),
(4, CURDATE(), 1, 'Walked to market instead of bike');

-- ------------------- AI RECOMMENDATIONS (demo, generated) -----------
INSERT INTO ai_recommendations (community_user_id, event_id, challenge_id, reason, score, engine_version) VALUES
(1, 5, 1, 'Matches wellness goal and yoga preference (Beginner level)', 92.00, 'rule-v1'),
(2, 8, 1, 'Matches running preference and weight-loss goal (Intermediate)', 88.00, 'rule-v1'),
(3, 5, 3, 'Yoga preference + flexibility goal — evening batch for working women', 90.00, 'rule-v1'),
(4, 7, NULL, 'Age-appropriate senior fitness program + walking preference', 86.00, 'rule-v1'),
(7, 6, 1, 'Nutrition workshop matches weight-loss goal; zumba for fun activity', 85.00, 'rule-v1');

-- =====================================================================
-- END OF DEMO SEED DATA
-- Remember: this is SAMPLE data for development and demonstration.
-- Real CEP results should come from real community participation.
-- =====================================================================
