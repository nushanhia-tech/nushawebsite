(function () {
    'use strict';

    function escapeText(value) {
        return String(value == null ? '' : value).replace(/[\u0000-\u001f\u007f]/g, ' ').trim();
    }

    function formatDate(value) {
        var match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value || ''));
        if (!match) return escapeText(value) || 'Not specified';
        var date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
        return new Intl.DateTimeFormat('en-GB', {
            day: '2-digit', month: 'short', year: 'numeric'
        }).format(date);
    }

    function reportTimestamp() {
        return new Intl.DateTimeFormat('en-GB', {
            timeZone: 'Asia/Kolkata', day: '2-digit', month: 'short', year: 'numeric',
            hour: '2-digit', minute: '2-digit', hour12: true
        }).format(new Date()) + ' IST';
    }

    function splitLines(context, value, maxWidth) {
        var characters = Array.from(escapeText(value));
        var lines = [];
        var line = '';
        characters.forEach(function (character) {
            var next = line + character;
            if (line && context.measureText(next).width > maxWidth) {
                lines.push(line);
                line = character;
            } else {
                line = next;
            }
        });
        if (line || !lines.length) lines.push(line);
        return lines;
    }

    function addPage(pdf, state) {
        if (state.page > 0) pdf.addPage();
        state.page += 1;
        var canvas = document.createElement('canvas');
        canvas.width = 1240;
        canvas.height = 1754;
        state.canvas = canvas;
        state.context = canvas.getContext('2d');
        var context = state.context;
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.fillStyle = '#0b1c3c';
        context.fillRect(0, 0, canvas.width, 148);
        context.fillStyle = '#fbbc05';
        context.fillRect(0, 148, canvas.width, 7);
        context.fillStyle = '#ffffff';
        context.font = '800 36px Montserrat, Arial, sans-serif';
        context.fillText("NUS'HA", 60, 65);
        context.font = '500 17px Montserrat, Arial, sans-serif';
        context.fillText("NOORUL HIDAYA STUDENTS' UNION", 60, 96);
        context.textAlign = 'right';
        context.font = '700 16px Montserrat, Arial, sans-serif';
        context.fillText('PROGRAMME CALENDAR REPORT', 1180, 62);
        context.font = '400 14px Montserrat, Arial, sans-serif';
        context.fillText('Generated: ' + state.generated, 1180, 92);
        context.textAlign = 'left';
        context.fillStyle = '#0b1c3c';
        context.font = '800 27px Montserrat, Arial, sans-serif';
        context.fillText('Programme schedule', 60, 213);
        context.fillStyle = '#64748b';
        context.font = '400 16px Montserrat, Arial, sans-serif';
        context.fillText(state.subtitle, 60, 243);
        context.fillStyle = '#f4f7f9';
        context.fillRect(60, 271, 1120, 47);
        context.fillStyle = '#52657d';
        context.font = '700 14px Montserrat, Arial, sans-serif';
        context.fillText('DATE & TIME', 76, 301);
        context.fillText('PROGRAMME', 280, 301);
        context.fillText('ORGANISING WING', 650, 301);
        context.fillText('CATEGORY / STATUS', 900, 301);
        context.textAlign = 'right';
        context.fillText('PAGE ' + state.page, 1162, 301);
        context.textAlign = 'left';
        state.y = 334;
    }

    function finishPage(pdf, state) {
        var context = state.context;
        context.strokeStyle = '#dce4ee';
        context.beginPath();
        context.moveTo(60, 1664);
        context.lineTo(1180, 1664);
        context.stroke();
        context.fillStyle = '#7a899c';
        context.font = '400 13px Montserrat, Arial, sans-serif';
        context.fillText("NUS'HA | Event Calendar Report", 60, 1695);
        context.textAlign = 'right';
        context.fillText('Confidential programme record', 1180, 1695);
        context.textAlign = 'left';
        pdf.addImage(state.canvas.toDataURL('image/jpeg', 0.96), 'JPEG', 0, 0, 210, 297);
        state.canvas.width = 1;
        state.canvas.height = 1;
    }

    function drawEvent(pdf, state, event) {
        var context = state.context;
        context.font = '700 17px Montserrat, Arial, sans-serif';
        var titleLines = splitLines(context, event.title || 'Untitled programme', 338);
        context.font = '500 15px Montserrat, Arial, sans-serif';
        var wingLines = splitLines(context, event.wing_name || 'Not specified', 215);
        var categoryLines = splitLines(context, (event.category || 'Not specified') + (event.stage_type ? ' | ' + event.stage_type : ''), 230);
        var rowHeight = Math.max(72, 27 + titleLines.length * 23 + 17, 27 + wingLines.length * 21 + 17, 27 + categoryLines.length * 19 + 38);
        if (state.y + rowHeight > 1628 && state.y > 334) {
            finishPage(pdf, state);
            addPage(pdf, state);
            context = state.context;
        }
        var y = state.y;
        if (event.status === 'Conducted') {
            context.fillStyle = '#f0fdf8';
        } else {
            context.fillStyle = '#fffaf0';
        }
        context.fillRect(60, y, 1120, rowHeight);
        context.fillStyle = '#0b1c3c';
        context.font = '700 16px Montserrat, Arial, sans-serif';
        context.fillText(formatDate(event.prog_date), 76, y + 28);
        context.font = '500 14px Montserrat, Arial, sans-serif';
        context.fillStyle = '#52657d';
        var timeText = escapeText(event.session_time) || 'Time not specified';
        if (escapeText(event.prog_time)) timeText += ' (' + escapeText(event.prog_time) + ')';
        splitLines(context, timeText, 176).forEach(function (line, index) {
            context.fillText(line, 76, y + 52 + index * 19);
        });
        context.fillStyle = '#0b1c3c';
        context.font = '700 17px Montserrat, Arial, sans-serif';
        titleLines.forEach(function (line, index) { context.fillText(line, 280, y + 28 + index * 23); });
        context.fillStyle = '#52657d';
        context.font = '500 15px Montserrat, Arial, sans-serif';
        wingLines.forEach(function (line, index) { context.fillText(line, 650, y + 28 + index * 21); });
        context.font = '500 14px Montserrat, Arial, sans-serif';
        categoryLines.forEach(function (line, index) { context.fillText(line, 900, y + 27 + index * 19); });
        context.fillStyle = event.status === 'Conducted' ? '#058568' : '#a66700';
        context.font = '700 14px Montserrat, Arial, sans-serif';
        context.fillText(escapeText(event.status) || 'Not specified', 900, y + rowHeight - 17);
        context.strokeStyle = '#dce4ee';
        context.beginPath();
        context.moveTo(60, y + rowHeight);
        context.lineTo(1180, y + rowHeight);
        context.stroke();
        state.y += rowHeight;
    }

    window.generatePDF = async function () {
        var button = document.querySelector('.pdf-btn');
        var status = document.getElementById('pdf-download-status');
        var events = Array.isArray(window.calendarReportEvents) ? window.calendarReportEvents : [];
        if (button) {
            button.disabled = true;
            button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Preparing report…';
        }
        if (status) status.textContent = 'Preparing all programme details with date, time, wing, category, and status…';
        try {
            await window.loadNushaPdf();
            if (document.fonts && document.fonts.ready) await document.fonts.ready;
            var conducted = events.filter(function (event) { return event.status === 'Conducted'; }).length;
            var approved = events.filter(function (event) { return event.status === 'Approved'; }).length;
            var subtitle = events.length
                ? events.length + ' programme' + (events.length === 1 ? '' : 's') + ' | ' + conducted + ' conducted | ' + approved + ' approved'
                : 'No approved or conducted programmes available';
            var pdf = new window.jspdf.jsPDF({orientation: 'portrait', unit: 'mm', format: 'a4', compress: true});
            pdf.setProperties({title: "NUS'HA Programme Calendar Report", author: "NUS'HA", subject: 'Approved and conducted programme schedule'});
            var state = {page: 0, generated: reportTimestamp(), subtitle: subtitle, canvas: null, context: null, y: 0};
            addPage(pdf, state);
            if (!events.length) {
                state.context.fillStyle = '#64748b';
                state.context.font = '500 18px Montserrat, Arial, sans-serif';
                state.context.fillText('No approved or conducted programmes are available.', 76, state.y + 34);
            } else {
                events.forEach(function (event) { drawEvent(pdf, state, event); });
            }
            finishPage(pdf, state);
            pdf.save('NUSHA-programme-calendar-report-' + new Date().toISOString().slice(0, 10) + '.pdf');
            if (status) status.textContent = 'Report download started. ' + events.length + ' programme records included across ' + state.page + ' page' + (state.page === 1 ? '' : 's') + '.';
        } catch (error) {
            console.error('PDF report generation failed:', error);
            if (status) status.textContent = 'The report could not be created. Please try again.';
        } finally {
            if (button) {
                button.disabled = false;
                button.innerHTML = '<i class="fa-solid fa-download"></i> Download Report PDF';
            }
        }
    };
})();
