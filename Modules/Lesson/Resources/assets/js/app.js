    //subject for lesson plan 


    $(document).ready(function() {
        // The subject list is narrowed by year level, and optionally by program and semester.
        // Any of the three changing reloads it: a stale list is how a lesson ends up saved
        // against the wrong program.
        function loadLessonSubjects() {
            var url = $("#url").val();

            var i = 0;
            var formData = {
                class_id: $("#select_class_lesson").val(),
                course_id: $("#select_lesson_program").val(),
                semester_id: $("#select_lesson_semester").val(),
            };

            if (!formData.class_id) {
                return;
            }

            $.ajax({
                type: "GET",
                data: formData,
                dataType: "json",
                url: url + "/lesson/" + "lessonSubject",

                beforeSend: function() {
                    $('#select_subject_loader').addClass('pre_loader');
                    $('#select_subject_loader').removeClass('loader');
                },

                success: function(data) {

                    $.each(data, function(i, item) {
                        $("#select_subject").find("option").not(":first").remove();
                        $("#select_subject_div ul").find("li").not(":first").remove();

                        if (item.length) {
                            $.each(item, function(i, subject) {
                                // The program code rides along with the name. Two programs
                                // sharing a subject name are otherwise identical lines with
                                // nothing to tell them apart.
                                var label = subject.course_code ?
                                    subject.subject_name + " [" + subject.course_code + "]" :
                                    subject.subject_name;

                                var type = subject.subject_type == "T" ? "Theory" : "Practical";

                                $("#select_subject").append(
                                    $("<option>", {
                                        value: subject.id,
                                        text: label,
                                    })
                                );

                                $("#select_subject_div ul").append(
                                    $("<li>", {
                                        "class": "option",
                                        "data-value": subject.id,
                                        text: label + " (" + type + ")",
                                    })
                                );

                            });
                        } else {
                            $("#select_subject_div .current").html("SELECT SUBJECT *");
                        }

                    });
                },
                error: function(data) {},
                complete: function() {
                    i--;
                    if (i <= 0) {
                        $('#select_subject_loader').removeClass('pre_loader');
                        $('#select_subject_loader').addClass('loader');
                    }
                }
            });
        }

        $("#select_class_lesson").on("change", loadLessonSubjects);
        $("#select_lesson_program").on("change", loadLessonSubjects);
        $("#select_lesson_semester").on("change", loadLessonSubjects);
    });
    //section for lesson plan
    $(document).ready(function() {
        $("#select_class_lesson_section").on("change", function() {
            var formData = {
                id: $(this).val(),
            };

            var url = $("#url").val();

            $.ajax({
                type: "GET",
                data: formData,
                dataType: "json",
                url: url + "/lesson/" + "lessonSection",
                success: function(data) {

                    $("#multiple-section").empty();

                    var appendRow = "";

                    appendRow += "<div class='col-lg-12'>";
                    appendRow += "<label>Select Section *</label>";
                    $.each(data, function(i, item) {
                        console.log(item);
                        $.each(item, function(i, value) {
                            appendRow += "<div class='input-effect'>";
                            appendRow +=
                                "<input type='checkbox' id='section_" +
                                value.id +
                                "' class='common-checkbox subject-checkbox' onChange='changeSubject(" + value.id + ")'  name='section_ids[]' value='" +
                                value.id +
                                "'>";
                            appendRow +=
                                "<label for='section_" +
                                value.id +
                                "'>" +
                                value.section_name +
                                "</label>";
                            appendRow += "</div>";

                        });
                    });

                    appendRow += "<div class='col-lg-12'>";
                    $("#multiple-section").append(appendRow);
                },
                error: function(data) {},
            });
        });
    });