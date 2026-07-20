define(['jquery', 'core/notification', 'core/ajax'], function($, Notification, Ajax) {
    return {
        init: function(courseId, aibackendurl) {
            console.log('local_coursetimeline init called with courseId: ' + courseId);
            var fetchedResources = [];
            var generatedTimelineData = null;
            var lastSelectedResources = [];

            // --- Function to Render Resources Table ---
            function renderResourcesTable(resources, autoCheck) {
                var canManage = $('.resource-finder-container').data('can-manage') == 1;
                var resultsDiv = $('#resource-results');
                var html = '';
                if (resources.length > 0) {
                    if (canManage) {
                        html += '<div class="alert alert-info">Select the resources you want to include in the timeline, then click "Generate Timeline".</div>';
                    }
                    html += '<div class="table-responsive">';
                    html += '<table class="table table-bordered table-striped table-hover" id="resources-table">';
                    html += '<thead class="thead-dark"><tr>';
                    if (canManage) {
                        html += '<th><input type="checkbox" id="select-all-resources" ' + (autoCheck ? 'checked' : '') + '></th>';
                    }
                    html += '<th>Title</th><th>Type</th><th>Duration</th><th>Link</th>';
                    html += '</tr></thead>';
                    html += '<tbody>';
                    
                    resources.forEach(function(resource, index) {
                        var title = resource.title || resource.name || 'Untitled';
                        var type = resource.type || resource._category || 'Resource';
                        var link = resource.link || resource.url || '#';
                        var resDuration = resource.duration || '-';
                        
                        html += '<tr>';
                        if (canManage) {
                            html += '<td><input type="checkbox" class="resource-checkbox" data-index="' + index + '" ' + (autoCheck ? 'checked' : '') + '></td>';
                        }
                        html += '<td>' + $('<div>').text(title).html() + '</td>';
                        html += '<td>' + $('<div>').text(type).html() + '</td>';
                        html += '<td>' + $('<div>').text(resDuration).html() + '</td>';
                        html += '<td><a href="' + link + '" target="_blank" class="btn btn-sm btn-info">View</a></td>';
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table></div>';
                    if (canManage) {
                        html += '<button type="button" id="generate-timeline-btn" class="btn btn-success btn-lg btn-block">Generate Timeline</button>';
                    }
                } else {
                     html = '<div class="alert alert-warning">No resources found.</div>';
                }

                resultsDiv.html(html);

                if (canManage) {
                    // Re-attach listener
                    attachGenerateHandler();
                    
                    // Select All handler
                    $('#select-all-resources').on('change', function() {
                        $('.resource-checkbox').prop('checked', $(this).is(':checked'));
                    });
                }
            }

            // --- Handler 0: Check for existing timeline ---
            // Load saved data on init
            var loadPromises = Ajax.call([{
                methodname: 'local_coursetimeline_get_timeline',
                args: { courseid: courseId }
            }]);

            loadPromises[0].then(function(response) {
                if (response.has_data) {
                    console.log('Found saved timeline data');
                    try {
                        var timelineData = JSON.parse(response.timeline_json);
                        var resourcesData = JSON.parse(response.resources_json);

                        if (timelineData) { // Resources might be empty but we should still show timeline
                            generatedTimelineData = timelineData;
                            
                            // Render Timeline
                            renderTimeline(generatedTimelineData);
                        }

                        if (resourcesData) {
                            lastSelectedResources = resourcesData;
                            fetchedResources = resourcesData;
                            // Render resources table, checked by default as these are the saved ones
                            renderResourcesTable(fetchedResources, true);
                        }

                    } catch (e) {
                        console.error('Error parsing saved data', e);
                    }
                }
            }).catch(function(error) {
                console.error('Error fetching saved timeline', error);
            });

            // --- Function to Render Timeline (refactored from generate handler) ---
            function renderTimeline(data) {
                var timelineDiv = $('#timeline-results');
                var timelineContent = $('#timeline-content');
                var html = '';
                
                // Helper to normalize data structure (same logic as before)
                var timelineArray = null;
                if (typeof data === 'string') { try { data = JSON.parse(data); } catch(e){} }
                
                if (Array.isArray(data)) timelineArray = data;
                else if (data.timeline && Array.isArray(data.timeline)) timelineArray = data.timeline;
                else if (data.weekly_plans && Array.isArray(data.weekly_plans)) timelineArray = data.weekly_plans;

                if (timelineArray) {
                    timelineDiv.show();
                    $('#save-timeline-btn').show();

                    html = '<div class="timeline">';
                    if (data.course_title || data.course_overview) {
                        html += '<div class="card mb-4 border-info"><div class="card-body bg-light">';
                        if (data.course_title) html += '<h3 class="card-title text-info">' + $('<div>').text(data.course_title).html() + '</h3>';
                        if (data.course_overview) html += '<p class="lead">' + $('<div>').text(data.course_overview).html() + '</p>';
                        if (data.course_level) html += '<p><strong>Level:</strong> ' + $('<div>').text(data.course_level).html() + '</p>';
                        html += '</div></div>';
                    }

                    html += '<div class="accordion" id="timelineAccordion">';

                    timelineArray.forEach(function(weekItem, idx) {
                        var weekNum = weekItem.week || weekItem.week_number || (idx + 1);
                        var title = weekItem.title || 'Untitled Week';
                        var learningObjs = weekItem.learning_objectives || [];
                        var topics = weekItem.topics || [];
                        var activities = weekItem.activities || [];
                        var assignedRes = weekItem.assigned_resources || [];
                        var assessment = weekItem.assessment || '';
                        
                        var headingId = 'heading' + idx;
                        var collapseId = 'collapse' + idx;

                        html += '<div class="card" style="border-radius: 4px; margin-bottom: 5px;">';
                        html += '<div class="card-header p-0" id="' + headingId + '">';
                        html += '<h5 class="mb-0">';
                        html += '<button class="btn btn-link btn-block text-left font-weight-bold text-decoration-none p-3" type="button" data-bs-toggle="collapse" data-bs-target="#' + collapseId + '" aria-expanded="true" aria-controls="' + collapseId + '" style="color: #333; background-color: #f8f9fa;">';
                        html += 'Week ' + weekNum + ': ' + $('<div>').text(title).html();
                        html += '</button>';
                        html += '</h5>';
                        html += '</div>';
                        
                        html += '<div id="' + collapseId + '" class="collapse" aria-labelledby="' + headingId + '" data-bs-parent="#timelineAccordion">';
                        html += '<div class="card-body">';
                        
                        if (topics.length > 0) {
                            html += '<h6 class="text-primary font-weight-bold">Topics:</h6><ul class="mb-3">';
                            topics.forEach(function(t) { html += '<li>' + $('<div>').text(t).html() + '</li>'; });
                            html += '</ul>';
                        }
                        if (learningObjs.length > 0) {
                            html += '<h6 class="text-success font-weight-bold">Learning Objectives:</h6><ul class="mb-3">';
                            learningObjs.forEach(function(obj) { html += '<li>' + $('<div>').text(obj).html() + '</li>'; });
                            html += '</ul>';
                        }
                        if (assignedRes.length > 0) {
                            html += '<h6 class="text-info font-weight-bold">Assigned Resources:</h6><div class="list-group mb-3">';
                            assignedRes.forEach(function(res) {
                                html += '<div class="list-group-item">';
                                html += '<strong>' + $('<div>').text(res.resource_name).html() + '</strong>';
                                if (res.suggested_sections) html += '<br><small class="text-muted">Section: ' + $('<div>').text(res.suggested_sections).html() + '</small>';
                                if (res.usage) html += '<br><i class="small">Usage: ' + $('<div>').text(res.usage).html() + '</i>';
                                html += '</div>';
                            });
                            html += '</div>';
                        }
                        if (activities.length > 0) {
                            html += '<h6 class="text-warning font-weight-bold" style="color:#d39e00!important">Activities:</h6><ul class="mb-3">';
                            activities.forEach(function(act) { html += '<li>' + $('<div>').text(act).html() + '</li>'; });
                            html += '</ul>';
                        }
                        if (assessment) {
                            html += '<div class="alert alert-secondary mb-0"><strong>Assessment:</strong> ' + $('<div>').text(assessment).html() + '</div>';
                        }
                        
                        html += '</div></div></div>';
                    });
                    html += '</div></div>';
                }
                timelineContent.html(html);
            }

            // --- Handler 1: Find Resources ---
            $('#find-resources-btn').on('click', function(e) {
                e.preventDefault();

                var topic = $('#course-topic').val();
                var resultsDiv = $('#resource-results');
                var timelineDiv = $('#timeline-results');

                timelineDiv.hide(); // Hide previous timeline if any

                // validation
                if (!topic) {
                    alert('Please enter a course topic');
                    return;
                }

                // Show loading spinner
                resultsDiv.html('<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div><p>Searching for resources...</p></div>');

                var payload = {
                    query: topic
                };

                fetch(aibackendurl + '/fetch_resources', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                })
                .then(function(response) {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(function(data) {
                    console.log('Fetch Resources Response:', data); // Debugging log

                    // Parse the new object structure from server
                    var flatResources = [];
                    if (data.resources) {
                        // Iterate categories (open_textbooks..., websites...)
                        Object.keys(data.resources).forEach(function(categoryKey) {
                            var categoryObj = data.resources[categoryKey];
                            if (categoryObj && typeof categoryObj === 'object') {
                                // Iterate items in category
                                Object.keys(categoryObj).forEach(function(itemKey) {
                                    var item = categoryObj[itemKey];
                                    // Make sure it looks like a resource
                                    if (item && (item.title || item.url)) {
                                        // Add metadata for our use
                                        item._id = itemKey; 
                                        item._category = categoryKey;
                                        flatResources.push(item);
                                    }
                                });
                            }
                        });
                    } else if (Array.isArray(data)) {
                        flatResources = data;
                    }

                    fetchedResources = flatResources; // Store globally for this module instance
                    
                    if (flatResources.length > 0) {
                        renderResourcesTable(flatResources, true);
                    } else {
                        var html = '<div class="alert alert-warning">No resources found for this topic. Raw dump: ' + JSON.stringify(data) + '</div>';
                        resultsDiv.html(html);
                    }
                })
                .catch(function(error) {
                    console.error('Error:', error);
                    resultsDiv.html('<div class="alert alert-danger">Error loading resources: ' + error.message + '</div>');
                });
            });

            // --- Handler 3: Save Timeline ---
            console.log('Attaching Save Timeline handler to #save-timeline-btn');
            $(document).on('click', '#save-timeline-btn', function(e) {
                console.log('Save Timeline button clicked');
                e.preventDefault();

                if (!generatedTimelineData) {
                    console.error('No timeline data to save (generatedTimelineData is null)');
                    Notification.alert('Error', 'No timeline data to save.');
                    return;
                }

                console.log('Saving timeline data:', generatedTimelineData);
                console.log('Saving resources data:', lastSelectedResources);

                var btn = $(this);
                btn.prop('disabled', true);

                var timelineJson = JSON.stringify(generatedTimelineData);
                var resourcesJson = JSON.stringify(lastSelectedResources);

                console.log('Sending AJAX request to local_coursetimeline_save_timeline');
                var promises = Ajax.call([{
                    methodname: 'local_coursetimeline_save_timeline',
                    args: {
                        courseid: courseId,
                        timeline_json: timelineJson,
                        resources_json: resourcesJson
                    }
                }]);

                promises[0].then(function(response) {
                    console.log('Save AJAX successful:', response);
                    Notification.addNotification({
                        message: 'Timeline saved successfully!',
                        type: 'success'
                    });
                    btn.prop('disabled', false);
                }).catch(function(error) {
                    console.error('Save AJAX failed:', error);
                    Notification.exception(error);
                    btn.prop('disabled', false);
                });
            });

            // --- Handler 2: Generate Timeline ---
            function attachGenerateHandler() {
                $('#generate-timeline-btn').on('click', function() {
                    var selectedResources = [];
                    $('.resource-checkbox:checked').each(function() {
                        var index = $(this).data('index');
                        if (fetchedResources[index]) {
                            selectedResources.push(fetchedResources[index]);
                        }
                    });

                    if (selectedResources.length === 0) {
                        alert('Please select at least one resource.');
                        return;
                    }

                    var timelineDiv = $('#timeline-results');
                    var timelineContent = $('#timeline-content');
                    var resultsDiv = $('#resource-results'); // We might want to keep the form disabled or something

                    timelineDiv.show();
                    timelineContent.html('<div class="text-center p-5"><div class="spinner-border text-success" style="width: 3rem; height: 3rem;" role="status"><span class="sr-only">Generating...</span></div><h4 class="mt-3">Generating Course Timeline...</h4><p class="text-muted">This involves complex AI processing and may take up to 300 seconds (5 minutes). Please do not close this page.</p></div>');

                    // Prepare payload
                    var duration = parseInt($('#course-duration').val()) || 4; // default 4
                    var level = $('#course-level').val() || 'Undergraduate';
                    var topic = $('#course-topic').val();

                    var payload = {
                        resources_json: { results: selectedResources },
                        course_duration_weeks: duration,
                        course_level: level,
                        course_title: topic
                    };
                    
                    // Store for saving later
                    lastSelectedResources = selectedResources; // This variable needs to be declared in init scope

                    // Implement 300s timeout
                    var controller = new AbortController();
                    var timeoutId = setTimeout(function() { controller.abort(); }, 300000);

                    fetch(aibackendurl + '/generate_timeline', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                        signal: controller.signal
                    })
                    .then(function(response) {
                        clearTimeout(timeoutId);
                        if (!response.ok) throw new Error('Server error: ' + response.statusText);
                        return response.json();
                    })
                    .then(function(data) {
                        var html = '';
                         if (data.error) {
                            html = '<div class="alert alert-danger">Generation Error: ' + data.error + '</div>';
                        } else {
                            // Helper to normalize data structure
                            var timelineArray = null;
                            
                            // 1. Try treating data as stringified JSON
                            if (typeof data === 'string') {
                                try { 
                                    var parsed = JSON.parse(data);
                                    if (Array.isArray(parsed)) timelineArray = parsed;
                                    else data = parsed; // Update data reference if it parsed to object
                                } catch(e) {}
                            }
                            
                            // 2. Direct Array
                            if (!timelineArray && Array.isArray(data)) {
                                timelineArray = data;
                            }
                            
                            // 3. Inside "timeline" property
                            if (!timelineArray && data.timeline) {
                                if (Array.isArray(data.timeline)) {
                                    timelineArray = data.timeline;
                                } else if (typeof data.timeline === 'string') {
                                    try {
                                        var parsedT = JSON.parse(data.timeline);
                                        if (Array.isArray(parsedT)) timelineArray = parsedT;
                                    } catch(e) {}
                                }
                            }
                            
                            // 4. Inside "weekly_plans" property (User's new format)
                            if (!timelineArray && data.weekly_plans) {
                                if (Array.isArray(data.weekly_plans)) {
                                    timelineArray = data.weekly_plans;
                                }
                            }

                            if (timelineArray) {
                                generatedTimelineData = data;
                                $('#save-timeline-btn').show();

                                renderTimeline(generatedTimelineData);
                            } else {
                                // Fallback: Show whatever raw data we have
                                var displayStr = (typeof data === 'string') ? data : JSON.stringify(data, null, 2);
                                html = '<div class="alert alert-warning">Could not parse timeline structure. Raw output:</div>';
                                html += '<div class="card card-body bg-light"><pre style="white-space: pre-wrap;">' + $('<div>').text(displayStr).html() + '</pre></div>';
                                timelineContent.html(html);
                            }
                        }
                    })
                    .catch(function(error) {
                        clearTimeout(timeoutId);
                        console.error('Error:', error);
                        var msg = error.message;
                        if (error.name === 'AbortError') {
                            msg = 'Request timed out after 300 seconds (5 minutes).';
                        }
                        timelineContent.html('<div class="alert alert-danger">Error generating timeline: ' + msg + '</div>');
                    });
                });
            }
        }
    };
});
