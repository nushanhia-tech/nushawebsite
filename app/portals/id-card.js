export const defaultCard = {
  width: 86,
  height: 54,
  background: "",
  photoTop: 19,
  photoLeft: 7,
  photoSize: 80,
  photoRadius: 10,
  nameTop: 29,
  nameLeft: 35,
  nameSize: 16,
  nameColor: "#ffffff",
  detailsTop: 52,
  detailsLeft: 35,
  detailsSize: 11,
  detailsColor: "#cbd5e1",
};

export function cardInitials(name) {
  return name
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part[0] || "")
    .join("")
    .toUpperCase();
}

export function CardPreview({ profile, design = defaultCard }) {
  const w = design.width;
  const box = (top, left) => ({ top: top + "%", left: left + "%" });
  const unit = (pixels) => (pixels / (w * 3.78)) * 100 + "cqw";
  return (
    <div
      className="id-card-preview"
      style={{
        width: w + "mm",
        aspectRatio: w + " / " + design.height,
        backgroundImage: design.background
          ? "url(" + JSON.stringify(design.background) + ")"
          : undefined,
      }}
    >
      <div
        className="id-card-photo"
        style={{
          ...box(design.photoTop, design.photoLeft),
          width: unit(design.photoSize),
          borderRadius: design.photoRadius + "%",
          fontSize: unit(design.photoSize / 3),
        }}
      >
        <span>{cardInitials(profile.display_name)}</span>
        {profile.photo_url && (
          <img
            src={profile.photo_url}
            alt=""
            loading="lazy"
            onError={(event) => {
              event.currentTarget.hidden = true;
            }}
          />
        )}
      </div>
      <strong
        className="id-card-copy"
        style={{
          ...box(design.nameTop, design.nameLeft),
          maxWidth: 100 - design.nameLeft + "%",
          color: design.nameColor,
          fontSize: unit(design.nameSize),
        }}
      >
        {profile.display_name}
      </strong>
      <span
        className="id-card-copy"
        style={{
          ...box(design.detailsTop, design.detailsLeft),
          maxWidth: 100 - design.detailsLeft + "%",
          color: design.detailsColor,
          fontSize: unit(design.detailsSize),
        }}
      >
        NUS’HA · {profile.member_number || "Student"}
      </span>
    </div>
  );
}
