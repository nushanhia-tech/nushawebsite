import { cardInitials } from "./id-card";

function loadImage(url) {
  if (!url) return Promise.resolve(null);
  return new Promise((resolve) => {
    const image = new Image();
    const timeout = setTimeout(() => resolve(null), 3500);
    const finish = (value) => {
      clearTimeout(timeout);
      resolve(value);
    };
    if (!url.startsWith("data:")) image.crossOrigin = "anonymous";
    image.onload = () => finish(image);
    image.onerror = () => finish(null);
    image.src = url;
  });
}

function cover(ctx, image, x, y, width, height) {
  const ratio = Math.max(width / image.width, height / image.height);
  const sourceWidth = width / ratio;
  const sourceHeight = height / ratio;
  ctx.drawImage(
    image,
    (image.width - sourceWidth) / 2,
    (image.height - sourceHeight) / 2,
    sourceWidth,
    sourceHeight,
    x,
    y,
    width,
    height,
  );
}

export async function downloadCards(design, profiles) {
  const [{ jsPDF }, backdrop] = await Promise.all([
    import("jspdf"),
    loadImage(design.background),
  ]);
  const pdf = new jsPDF({ compress: true });
  const canvas = document.createElement("canvas");
  canvas.width = Math.round(design.width * 9);
  canvas.height = Math.round(design.height * 9);
  const ctx = canvas.getContext("2d");
  const width = canvas.width;
  const height = canvas.height;
  const scale = width / (design.width * 3.78);
  const columns = Math.max(1, Math.floor(190 / (design.width + 4)));
  const rows = Math.max(1, Math.floor(277 / (design.height + 4)));
  const cardsPerPage = rows * columns;
  let missingPhotos = 0;
  for (let offset = 0; offset < profiles.length; offset += 12) {
    const batch = profiles.slice(offset, offset + 12);
    const photos = await Promise.all(
      batch.map((profile) => loadImage(profile.photo_url)),
    );
    batch.forEach((profile, index) => {
      const absoluteIndex = offset + index;
      const position = absoluteIndex % cardsPerPage;
      if (absoluteIndex && position === 0) pdf.addPage();
      const photo = photos[index];
      if (profile.photo_url && !photo) missingPhotos++;
      ctx.fillStyle = "#14274b";
      ctx.fillRect(0, 0, width, height);
      if (backdrop) cover(ctx, backdrop, 0, 0, width, height);
      const x = (width * design.photoLeft) / 100;
      const y = (height * design.photoTop) / 100;
      const size = design.photoSize * scale;
      ctx.save();
      ctx.beginPath();
      ctx.roundRect(x, y, size, size, (size * design.photoRadius) / 100);
      ctx.clip();
      if (photo) cover(ctx, photo, x, y, size, size);
      else {
        ctx.fillStyle = "#dbeafe";
        ctx.fillRect(x, y, size, size);
        ctx.fillStyle = "#14274b";
        ctx.font = "700 " + size / 3 + "px system-ui";
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        ctx.fillText(
          cardInitials(profile.display_name),
          x + size / 2,
          y + size / 2,
        );
      }
      ctx.restore();
      ctx.textBaseline = "top";
      ctx.font = "700 " + design.nameSize * scale + "px system-ui";
      ctx.fillStyle = design.nameColor;
      ctx.fillText(
        profile.display_name,
        (width * design.nameLeft) / 100,
        (height * design.nameTop) / 100,
        (width * (100 - design.nameLeft)) / 100 - 4,
      );
      ctx.font = design.detailsSize * scale + "px system-ui";
      ctx.fillStyle = design.detailsColor;
      ctx.fillText(
        "NUS’HA · " + (profile.member_number || "Student"),
        (width * design.detailsLeft) / 100,
        (height * design.detailsTop) / 100,
        (width * (100 - design.detailsLeft)) / 100 - 4,
      );
      pdf.addImage(
        canvas.toDataURL("image/jpeg", 0.88),
        "JPEG",
        10 + (position % columns) * (design.width + 4),
        10 + Math.floor(position / columns) * (design.height + 4),
        design.width,
        design.height,
      );
    });
  }
  pdf.save("nusha-student-id-cards.pdf");
  return missingPhotos;
}

export async function downloadProgrammeReport(items, type) {
  const { jsPDF } = await import("jspdf");
  const pdf = new jsPDF({ compress: true });
  const heading =
    type === "calendar"
      ? "Official programme calendar"
      : "Official programme results";
  const canvas = document.createElement("canvas");
  canvas.width = 1000;
  canvas.height = 1414;
  const ctx = canvas.getContext("2d");
  let y;
  function begin() {
    ctx.fillStyle = "#fff";
    ctx.fillRect(0, 0, 1000, 1414);
    ctx.fillStyle = "#14274b";
    ctx.font = "700 32px system-ui";
    ctx.fillText(heading, 58, 90);
    ctx.font = "18px system-ui";
    ctx.fillText("NUS’HA Student Union", 58, 125);
    y = 174;
  }
  function finish() {
    pdf.addImage(canvas.toDataURL("image/jpeg", 0.9), "JPEG", 0, 0, 210, 297);
  }
  function lines(value) {
    const rows = [];
    let line = "";
    for (const word of String(value).split(/\s+/)) {
      if (line && ctx.measureText(line + " " + word).width > 880) {
        rows.push(line);
        line = word;
      } else line = line ? line + " " + word : word;
    }
    if (line) rows.push(line);
    return rows;
  }
  begin();
  if (!items.length) {
    ctx.font = "20px system-ui";
    ctx.fillText("No entries to report.", 58, y);
  }
  for (const item of items) {
    ctx.font = "18px system-ui";
    const details = lines(item.detail || item.status);
    if (y + details.length * 27 + 72 > 1340) {
      finish();
      pdf.addPage();
      begin();
    }
    ctx.fillStyle = "#14274b";
    ctx.font = "700 21px system-ui";
    ctx.fillText(item.title, 58, y, 884);
    ctx.font = "18px system-ui";
    ctx.fillStyle = "#445674";
    const subtitle = [item.scheduled_on, item.status, item.sender]
      .filter(Boolean)
      .join(" · ");
    ctx.fillText(subtitle, 58, y + 27, 884);
    details.forEach((line, index) =>
      ctx.fillText(line, 58, y + 54 + index * 27, 884),
    );
    y += 68 + details.length * 27;
  }
  finish();
  pdf.save(
    type === "calendar"
      ? "nusha-programme-calendar.pdf"
      : "nusha-programme-results.pdf",
  );
}
