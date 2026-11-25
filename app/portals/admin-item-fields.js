import PhotoUpload from "./photo-upload";

export function OutreachFields({ details = {}, create, db }) {
  return (
    <>
      <label>
        Category
        <select name="category" defaultValue={details.category || "Article"}>
          <option>Article</option>
          <option>Story</option>
          <option>Poem</option>
        </select>
      </label>
      <label>
        Author name
        <input
          name="author_name"
          defaultValue={details.author_name ?? ""}
          maxLength={160}
        />
      </label>
      <label>
        Author role
        <input
          name="author_role"
          defaultValue={details.author_role ?? ""}
          maxLength={160}
        />
      </label>
      <PhotoUpload db={db} name="thumbnail" label="Post image" value={details.thumbnail ?? ""} />
      <PhotoUpload db={db} name="author_photo" label="Author photo" value={details.author_photo ?? ""} />
      {create && (
        <label className="confirmation">
          <input type="checkbox" name="publish" value="true" /> Publish
          immediately
        </label>
      )}
    </>
  );
}

export function AdminEditFields({ item, db }) {
  const details = item.admin_details ?? {};
  return (
    <>
      <label>
        Title
        <input
          name="title"
          defaultValue={item.title}
          required
          maxLength={160}
        />
      </label>
      <label>
        {item.kind === "outreach" ? "Post content" : "Description"}
        <textarea
          name="detail"
          defaultValue={item.detail}
          required
          maxLength={2000}
          rows={5}
        />
      </label>
      {item.kind === "outreach" ? (
        <OutreachFields details={item.public_details ?? {}} db={db} />
      ) : (
        <>
          <label>
            Programme date
            <input
              name="scheduled_on"
              type="date"
              defaultValue={item.scheduled_on ?? ""}
            />
          </label>
          <label>
            Time
            <input name="time" type="time" defaultValue={details.time ?? ""} />
          </label>
          <label>
            Category
            <input
              name="category"
              defaultValue={details.category ?? ""}
              maxLength={120}
            />
          </label>
          <label>
            Stage
            <select name="stage" defaultValue={details.stage ?? ""}>
              <option value="">Unspecified</option>
              <option>Stage</option>
              <option>Off-Stage</option>
            </select>
          </label>
          <label>
            Submission month
            <input
              name="month"
              type="month"
              defaultValue={details.month ?? ""}
            />
          </label>
          <label>
            File URL
            <input
              name="file"
              type="url"
              defaultValue={details.file ?? ""}
              maxLength={1000}
            />
          </label>
          <label>
            Points
            <input
              name="score"
              type="number"
              min="0"
              max="100"
              defaultValue={item.score ?? ""}
            />
          </label>
        </>
      )}
    </>
  );
}
